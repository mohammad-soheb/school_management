// ============================================================================
// File drop-zone (<x-form.file>)
// Shows the chosen file names/sizes and supports drag & drop onto the zone.
// ============================================================================

const formatBytes = (bytes) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export function registerFileInput(Alpine) {
    Alpine.data('fileInput', (config = {}) => ({
        files: [],
        dragging: false,
        multiple: Boolean(config.multiple),

        refresh() {
            this.files = Array.from(this.$refs.input.files ?? []).map((file) => ({ name: file.name, size: formatBytes(file.size) }));
        },

        drop(event) {
            this.dragging = false;
            const dropped = event.dataTransfer?.files;

            if (!dropped || dropped.length === 0) {
                return;
            }

            try {
                const transfer = new DataTransfer();
                Array.from(dropped)
                    .slice(0, this.multiple ? dropped.length : 1)
                    .forEach((file) => transfer.items.add(file));
                this.$refs.input.files = transfer.files;
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch {
                // Older browsers cannot assign files programmatically; the click-to-browse path still works.
            }

            this.refresh();
        },

        clear() {
            this.$refs.input.value = '';
            this.files = [];
        },
    }));
}
