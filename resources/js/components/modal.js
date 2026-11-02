// ============================================================================
// Modal state (<x-ui.modal>)
// ----------------------------------------------------------------------------
// Open from anywhere:   $dispatch('open-modal', 'name')   /  window.dispatchEvent(new CustomEvent('open-modal', { detail: 'name' }))
// Close:                $dispatch('close-modal', 'name')  (or without a name to close every modal), Esc, backdrop click.
// Focus is trapped inside the dialog while it is open (x-trap) and returns to the opener on close.
// ============================================================================

export function registerModal(Alpine) {
    Alpine.data('modal', (config = {}) => ({
        name: config.name ?? '',
        open: Boolean(config.show),
        opener: null,

        onOpen(event) {
            if (event.detail === this.name) {
                this.opener = document.activeElement;
                this.open = true;
            }
        },

        onClose(event) {
            if (!event.detail || event.detail === this.name) {
                this.close();
            }
        },

        close() {
            if (!this.open) {
                return;
            }

            this.open = false;
            const opener = this.opener;
            this.opener = null;

            // Hand focus back to whatever opened the dialog.
            this.$nextTick(() => opener?.isConnected && opener.focus?.());
        },
    }));
}
