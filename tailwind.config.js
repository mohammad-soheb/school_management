import defaultTheme from 'tailwindcss/defaultTheme';
import plugin from 'tailwindcss/plugin';

// Colours that change between light and dark mode are CSS variables holding
// "R G B" triplets (see resources/scss/abstracts/_tokens.scss). That keeps
// alpha utilities such as `bg-brand-500/10` or `ring-focus/20` working in both
// themes, so views almost never need a `dark:` variant.
const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

const TONES = ['neutral', 'brand', 'success', 'warning', 'danger', 'info'];

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
        './storage/framework/views/*.php',
    ],

    // Component classes that are assembled from a variable (tone, size, ...) or toggled by JS.
    // The Blade components spell their class names out literally, so this is only a safety net.
    safelist: [
        ...['badge', 'alert', 'stat-icon', 'toast', 'progress', 'dot'].flatMap((name) => TONES.map((tone) => `${name}-${tone}`)),
        ...Array.from({ length: 8 }, (_, index) => `avatar-tone-${index}`),
        ...['xs', 'sm', 'md', 'lg', 'xl'].map((size) => `avatar-${size}`),
        ...['primary', 'secondary', 'ghost', 'danger', 'success', 'sm', 'md', 'lg'].map((name) => `btn-${name}`),
        ...['sm', 'md', 'lg', 'xl'].map((size) => `modal-${size}`),
        'is-loading',
        'is-active',
        'is-open',
        'is-invalid',
    ],

    future: {
        // Wrap hover styles in @media (hover: hover) so taps don't leave sticky hover states on phones.
        hoverOnlyWhenSupported: true,
    },

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem', letterSpacing: '0.04em' }],
            },
            colors: {
                canvas: token('c-canvas'),
                surface: {
                    DEFAULT: token('c-surface'),
                    muted: token('c-surface-muted'),
                    raised: token('c-surface-raised'),
                },
                field: token('c-field'),
                line: {
                    DEFAULT: token('c-line'),
                    strong: token('c-line-strong'),
                },
                ink: {
                    DEFAULT: token('c-ink'),
                    soft: token('c-ink-soft'),
                    faint: token('c-ink-faint'),
                },
                focus: token('c-focus'),
                scrim: token('c-scrim'),
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                    // Brand-coloured text/icons on a surface: dark indigo in light mode, pale indigo in dark mode.
                    ink: token('c-brand-ink'),
                },
                success: { DEFAULT: token('c-success'), ink: token('c-success-ink') },
                warning: { DEFAULT: token('c-warning'), ink: token('c-warning-ink') },
                danger: { DEFAULT: token('c-danger'), ink: token('c-danger-ink') },
                info: { DEFAULT: token('c-info'), ink: token('c-info-ink') },
            },
            // Plain `border`, `divide-x`, `ring` ... pick up the hairline colour instead of Tailwind's grey-200.
            borderColor: ({ theme }) => ({ ...theme('colors'), DEFAULT: token('c-line') }),
            divideColor: ({ theme }) => ({ ...theme('colors'), DEFAULT: token('c-line') }),
            ringColor: ({ theme }) => ({ ...theme('colors'), DEFAULT: 'rgb(var(--c-focus) / 0.45)' }),
            ringOffsetColor: ({ theme }) => ({ ...theme('colors'), DEFAULT: 'rgb(var(--c-surface))' }),
            boxShadow: {
                // Defined per theme in _tokens.scss so shadows get heavier in dark mode.
                card: 'var(--shadow-card)',
                pop: 'var(--shadow-pop)',
                glow: 'var(--shadow-glow)',
                btn: 'var(--shadow-btn)',
            },
            borderRadius: {
                xl: '0.875rem',
                '2xl': '1.125rem',
                '3xl': '1.5rem',
            },
            minHeight: { touch: '2.75rem' },
            minWidth: { touch: '2.75rem' },
            maxWidth: { content: '80rem' },
            transitionTimingFunction: {
                out: 'cubic-bezier(0.22, 1, 0.36, 1)',
            },
            keyframes: {
                // NB: no `transform` is left behind after the animation (see `backwards` below), otherwise the
                // animated wrapper would become the containing block of position:fixed descendants (modals).
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(6px)' },
                    '100%': { opacity: '1', transform: 'none' },
                },
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'toast-in': {
                    '0%': { opacity: '0', transform: 'translateY(8px) scale(0.98)' },
                    '100%': { opacity: '1', transform: 'none' },
                },
                shimmer: {
                    '100%': { transform: 'translateX(100%)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.32s cubic-bezier(0.22, 1, 0.36, 1) backwards',
                'fade-in': 'fade-in 0.2s ease-out backwards',
                'toast-in': 'toast-in 0.24s cubic-bezier(0.22, 1, 0.36, 1) backwards',
                shimmer: 'shimmer 1.6s infinite',
            },
        },
    },

    plugins: [
        plugin(({ addVariant, addUtilities }) => {
            // `coarse:` -> touch screens; `fine:` -> mouse / trackpad.
            addVariant('coarse', '@media (pointer: coarse)');
            addVariant('fine', '@media (pointer: fine)');
            // `loading:` -> element currently carrying the `is-loading` class added by the submit lock.
            addVariant('loading', '&.is-loading');

            addUtilities({
                '.pt-safe': { paddingTop: 'env(safe-area-inset-top)' },
                '.pb-safe': { paddingBottom: 'env(safe-area-inset-bottom)' },
                '.scrollbar-none': { scrollbarWidth: 'none', '&::-webkit-scrollbar': { display: 'none' } },
            });
        }),
    ],
};
