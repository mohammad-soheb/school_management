// ============================================================================
// Confirm
// ----------------------------------------------------------------------------
// 1. x-data="confirm({ name })": the trigger side of <x-ui.confirm>. Clicking the
//    trigger opens the confirm modal with that name (the modal itself is <x-ui.modal>).
// 2. x-confirm="'Message'": quick native confirm() for places where a modal is
//    overkill, e.g.   <button type="submit" x-confirm="'Remove this row?'">
//    or               <form x-confirm="'Send to all parents?'">
//    Cancelling stops the click / submit before any other handler runs.
// ============================================================================

export function registerConfirm(Alpine) {
    Alpine.data('confirm', (config = {}) => ({
        name: config.name ?? '',

        show() {
            this.$dispatch('open-modal', this.name);
        },

        hide() {
            this.$dispatch('close-modal', this.name);
        },
    }));

    Alpine.directive('confirm', (el, { expression }, { evaluate, cleanup }) => {
        const type = el.tagName === 'FORM' ? 'submit' : 'click';

        const handler = (event) => {
            const message = expression ? evaluate(expression) : 'Are you sure?';

            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        };

        // Capture phase: runs before the element's own and Alpine's @click handlers.
        el.addEventListener(type, handler, true);
        cleanup(() => el.removeEventListener(type, handler, true));
    });
}
