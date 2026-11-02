// ============================================================================
// Toasts
// ----------------------------------------------------------------------------
// Markup: resources/views/components/ui/toasts.blade.php. Flash messages from
// the session are passed in as the initial list; scripts can add more:
//
//   window.toast('Saved', 'success');
//   window.dispatchEvent(new CustomEvent('toast', { detail: { tone: 'danger', message: 'Failed', title: 'Oops' } }));
//
// Tones: success | info | warning | danger. Errors stay longer and use role="alert".
// The class names below are written out in full so Tailwind's content scan keeps them.
// ============================================================================

const TONE_CLASS = {
    success: 'toast-success',
    info: 'toast-info',
    warning: 'toast-warning',
    danger: 'toast-danger',
};

const DURATION = { success: 5000, info: 6000, warning: 8000, danger: 10000 };

export function registerToasts(Alpine) {
    Alpine.data('toastStack', (initial = []) => ({
        items: [],
        timers: new Map(),
        seq: 0,

        init() {
            initial.forEach((item) => this.push(item));
        },

        push(detail = {}) {
            const tone = TONE_CLASS[detail.tone] ? detail.tone : 'info';
            const id = ++this.seq;

            this.items.push({
                id,
                tone,
                cls: TONE_CLASS[tone],
                title: detail.title ?? '',
                message: detail.message ?? '',
                role: tone === 'danger' || tone === 'warning' ? 'alert' : 'status',
            });

            this.arm(id, detail.duration ?? DURATION[tone]);
        },

        arm(id, ms) {
            if (!ms) {
                return;
            }

            this.timers.set(id, window.setTimeout(() => this.dismiss(id), ms));
        },

        pause(id) {
            window.clearTimeout(this.timers.get(id));
            this.timers.delete(id);
        },

        resume(id) {
            this.arm(id, 3000);
        },

        dismiss(id) {
            this.pause(id);
            this.items = this.items.filter((item) => item.id !== id);
        },
    }));

    // Convenience for module scripts.
    window.toast = (message, tone = 'info', title = '') => {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message, tone, title } }));
    };
}
