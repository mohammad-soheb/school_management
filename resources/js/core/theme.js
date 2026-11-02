// ============================================================================
// Theme (dark mode)
// ----------------------------------------------------------------------------
// The class on <html> is applied BEFORE first paint by the small inline script
// in resources/views/components/layouts/partials/head.blade.php (no flash).
// This store only handles later changes: the toggle button, OS preference
// changes and changes made in another tab.
//
//   <button x-data @click="$store.theme.toggle()">...</button>
//   $store.theme.dark   -> boolean
//   window 'theme-changed' event -> { detail: { dark } }  (charts listen to it)
// ============================================================================

const KEY = 'theme';
const root = document.documentElement;

const read = () => {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
};

const write = (value) => {
    try {
        if (value === null) {
            localStorage.removeItem(KEY);
        } else {
            localStorage.setItem(KEY, value);
        }
    } catch {
        // Private mode / blocked storage: the choice just won't persist.
    }
};

const osPrefersDark = () => (window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)').matches : false);

export function registerTheme(Alpine) {
    Alpine.store('theme', {
        dark: root.classList.contains('dark'),

        init() {
            // Follow the OS while the user has not made an explicit choice.
            if (window.matchMedia) {
                const media = window.matchMedia('(prefers-color-scheme: dark)');
                const onChange = (event) => {
                    if (read() === null) {
                        this.apply(event.matches);
                    }
                };

                if (media.addEventListener) {
                    media.addEventListener('change', onChange);
                } else if (media.addListener) {
                    media.addListener(onChange); // Safari < 14
                }
            }

            // Keep several open tabs in sync.
            window.addEventListener('storage', (event) => {
                if (event.key === KEY) {
                    this.apply(event.newValue === null ? osPrefersDark() : event.newValue === 'dark');
                }
            });
        },

        apply(dark) {
            this.dark = dark;
            root.classList.toggle('dark', dark);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
        },

        set(mode) {
            if (mode === 'system') {
                write(null);
                this.apply(osPrefersDark());

                return;
            }

            write(mode === 'dark' ? 'dark' : 'light');
            this.apply(mode === 'dark');
        },

        toggle() {
            this.set(this.dark ? 'light' : 'dark');
        },
    });
}
