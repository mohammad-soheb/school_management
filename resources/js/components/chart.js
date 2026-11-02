// ============================================================================
// Chart  (Chart.js 4, loaded lazily)
// ----------------------------------------------------------------------------
//   <div x-data="chart({ type: 'bar', labels: [...], datasets: [...], options: {...}, height: 280 })">
//       <canvas x-ref="canvas"></canvas>
//   </div>
// (use <x-ui.chart> in Blade). chart.js is only downloaded when a chart is on
// the page, and only once it scrolls near the viewport. Colours are read from
// the design tokens (CSS variables), so charts follow light/dark mode and are
// rebuilt when the theme changes.
// ============================================================================

// Categorical palette: indigo, sky, emerald, amber, rose, violet, teal, orange.
const PALETTE = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#f43f5e', '#8b5cf6', '#14b8a6', '#f97316'];

const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// "107 114 128" (token value) -> "rgba(107, 114, 128, 1)"
function token(name, alpha = 1) {
    const raw = getComputedStyle(document.documentElement).getPropertyValue(`--${name}`).trim();
    const [r = 0, g = 0, b = 0] = raw.split(/\s+/).map(Number);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function hexToRgba(hex, alpha) {
    const value = hex.replace('#', '');
    const n = Number.parseInt(value.length === 3 ? value.replace(/./g, '$&$&') : value, 16);

    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

const isPlainObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

function merge(target, source) {
    const out = { ...target };

    Object.entries(source ?? {}).forEach(([key, value]) => {
        out[key] = isPlainObject(value) && isPlainObject(out[key]) ? merge(out[key], value) : value;
    });

    return out;
}

function decorate(type, dataset, index, colors) {
    const base = PALETTE[index % PALETTE.length];
    const ds = { ...dataset };

    if (type === 'doughnut' || type === 'pie') {
        ds.backgroundColor ??= PALETTE;
        ds.borderColor ??= colors.surface;
        ds.borderWidth ??= 3;
        ds.hoverOffset ??= 6;

        return ds;
    }

    if (type === 'line') {
        const stroke = ds.borderColor ?? base;
        ds.borderColor = stroke;
        ds.borderWidth ??= 2.5;
        ds.tension ??= 0.35;
        ds.pointRadius ??= 0;
        ds.pointHoverRadius ??= 5;
        ds.pointHoverBackgroundColor ??= stroke;
        ds.pointHoverBorderColor ??= colors.surface;
        ds.pointHoverBorderWidth ??= 2;
        ds.fill ??= true;

        if (ds.backgroundColor === undefined && ds.fill) {
            ds.backgroundColor = (context) => {
                const { chart } = context;

                if (!chart.chartArea) {
                    return 'transparent';
                }

                const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                gradient.addColorStop(0, hexToRgba(stroke.startsWith('#') ? stroke : base, 0.22));
                gradient.addColorStop(1, hexToRgba(stroke.startsWith('#') ? stroke : base, 0));

                return gradient;
            };
        }

        return ds;
    }

    // bar (default)
    ds.backgroundColor ??= base;
    ds.borderRadius ??= 6;
    ds.borderSkipped ??= false;
    ds.maxBarThickness ??= 36;

    return ds;
}

function build(config, animate) {
    const type = config.type ?? 'bar';
    const circular = type === 'doughnut' || type === 'pie';
    const colors = {
        ink: token('c-ink-soft'),
        faint: token('c-ink-faint'),
        line: token('c-line'),
        surface: token('c-surface-raised'),
        strong: token('c-ink'),
    };
    const datasets = (config.datasets ?? []).map((dataset, index) => decorate(type, dataset, index, colors));

    const base = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: circular ? 'nearest' : 'index', intersect: false },
        animation: animate && !prefersReducedMotion() ? { duration: 600, easing: 'easeOutQuart' } : false,
        layout: { padding: { top: 4 } },
        plugins: {
            legend: {
                display: circular || datasets.length > 1,
                position: circular ? 'bottom' : 'top',
                align: circular ? 'center' : 'end',
                labels: {
                    color: colors.ink,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 8,
                    boxHeight: 8,
                    padding: 16,
                    font: { size: 12, family: 'inherit' },
                },
            },
            tooltip: {
                backgroundColor: colors.surface,
                titleColor: colors.strong,
                bodyColor: colors.ink,
                borderColor: colors.line,
                borderWidth: 1,
                padding: 10,
                cornerRadius: 10,
                boxPadding: 4,
                usePointStyle: true,
                titleFont: { weight: '600', family: 'inherit' },
                bodyFont: { family: 'inherit' },
            },
        },
    };

    if (circular) {
        base.cutout = type === 'doughnut' ? '68%' : 0;
    } else {
        base.scales = {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { color: colors.faint, font: { size: 12, family: 'inherit' }, maxRotation: 0, autoSkipPadding: 12 },
            },
            y: {
                beginAtZero: true,
                grid: { color: colors.line },
                border: { display: false, dash: [3, 4] },
                ticks: { color: colors.faint, font: { size: 12, family: 'inherit' }, padding: 8, maxTicksLimit: 6 },
            },
        };
    }

    return {
        type,
        data: { labels: config.labels ?? [], datasets },
        options: merge(base, config.options ?? {}),
    };
}

export function registerChart(Alpine) {
    Alpine.data('chart', (config = {}) => ({
        chart: null,
        observer: null,
        onTheme: null,

        init() {
            if (config.height) {
                this.$el.style.height = typeof config.height === 'number' ? `${config.height}px` : String(config.height);
            }

            this.$el.classList.add('is-loading');

            // Load chart.js when the chart is about to scroll into view.
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver(
                    (entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) {
                            this.observer.disconnect();
                            this.mount();
                        }
                    },
                    { rootMargin: '240px' },
                );
                this.observer.observe(this.$el);
            } else {
                this.mount();
            }

            this.onTheme = () => this.rebuild();
            window.addEventListener('theme-changed', this.onTheme);
        },

        async mount() {
            const { default: Chart } = await import('chart.js/auto');
            this.Chart = Chart;
            this.chart = new Chart(this.$refs.canvas, build(config, true));
            this.$el.classList.remove('is-loading');
        },

        // Colours come from CSS variables, so a theme switch needs a fresh chart.
        rebuild() {
            if (!this.chart || !this.Chart) {
                return;
            }

            this.chart.destroy();
            this.chart = new this.Chart(this.$refs.canvas, build(config, false));
        },

        destroy() {
            this.observer?.disconnect();
            window.removeEventListener('theme-changed', this.onTheme);
            this.chart?.destroy();
        },
    }));
}
