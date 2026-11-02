// ============================================================================
// Dropdown menu (<x-ui.dropdown>)
// ----------------------------------------------------------------------------
// The first button / link inside the trigger slot becomes the menu button
// (aria-haspopup / aria-expanded are managed here). Items are elements with
// role="menuitem". Keyboard: ArrowDown opens + moves, ArrowUp moves back,
// Home/End jump, Esc closes and returns focus to the button, Tab closes.
// ============================================================================

export function registerDropdown(Alpine) {
    Alpine.data('dropdown', () => ({
        open: false,
        button: null,

        init() {
            this.button = this.$refs.trigger.querySelector('button, a[href], [tabindex]') ?? this.$refs.trigger;
            this.button.setAttribute('aria-haspopup', 'menu');
            this.button.setAttribute('aria-expanded', 'false');

            this.$watch('open', (value) => {
                this.button.setAttribute('aria-expanded', value ? 'true' : 'false');

                if (value) {
                    // Wait for x-show to display the panel before moving focus into it.
                    this.$nextTick(() => this.items()[0]?.focus());
                }
            });
        },

        items() {
            return Array.from(this.$refs.panel.querySelectorAll('[role="menuitem"]:not([disabled])'));
        },

        toggle() {
            this.open = !this.open;
        },

        openMenu() {
            this.open = true;
        },

        close(returnFocus = true) {
            if (!this.open) {
                return;
            }

            this.open = false;

            if (returnFocus) {
                this.button.focus();
            }
        },

        move(delta) {
            const items = this.items();

            if (items.length === 0) {
                return;
            }

            const index = items.indexOf(document.activeElement);
            items[(index + delta + items.length) % items.length].focus();
        },

        edge(last) {
            const items = this.items();
            (last ? items[items.length - 1] : items[0])?.focus();
        },
    }));
}
