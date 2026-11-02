// ============================================================================
// Submit lock (double-submit protection)
// ----------------------------------------------------------------------------
// On the first `submit` of a form:
//   * a second submit of the same form is ignored (preventDefault),
//   * its submit buttons are disabled on the next tick (setTimeout 0) so the
//     clicked button's name/value is still part of the posted data,
//   * the clicked button gets the `is-loading` class (spinner, see _button.scss).
// The lock is released on `pageshow` (back/forward cache restores the page with
// the form still locked) and, as a safety net for downloads that never leave
// the page, after `data-lock-timeout` ms (default 30 000).
//
// Opt out per form:  <form data-lock="false">
// This is UX only. Money-moving endpoints must still be idempotent server-side
// (see <x-form.idempotency>).
// ============================================================================

const DEFAULT_TIMEOUT = 30000;

const submitButtons = (form) =>
    Array.from(form.elements ?? []).filter(
        (el) => (el.tagName === 'BUTTON' && (el.type === 'submit' || !el.getAttribute('type'))) || (el.tagName === 'INPUT' && el.type === 'submit'),
    );

function lock(form, submitter) {
    form.dataset.submitting = 'true';

    // Next tick: the browser has already collected the form data by then.
    setTimeout(() => {
        const buttons = submitButtons(form);

        // Buttons outside the form that point at it with form="id".
        if (form.id) {
            document.querySelectorAll(`button[form="${CSS.escape(form.id)}"]`).forEach((button) => buttons.push(button));
        }

        buttons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });

        const target = submitter && buttons.includes(submitter) ? submitter : buttons[0];
        target?.classList.add('is-loading');
        form.setAttribute('aria-busy', 'true');
    }, 0);

    const timeout = Number.parseInt(form.dataset.lockTimeout ?? '', 10) || DEFAULT_TIMEOUT;
    window.setTimeout(() => unlock(form), timeout);
}

function unlock(form) {
    delete form.dataset.submitting;
    form.removeAttribute('aria-busy');

    submitButtons(form).forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-disabled');
        button.classList.remove('is-loading');
    });
}

export function registerSubmitLock() {
    // Bubble phase on document: handlers on the form itself (Alpine @submit.prevent, validation)
    // have already run, so `defaultPrevented` tells us whether the submission really goes ahead.
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.lock === 'false') {
            return;
        }

        // A form that opens elsewhere (target=_blank) never reloads this page, so locking would strand it.
        if (form.target && form.target !== '_self') {
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();

            return;
        }

        lock(form, event.submitter);
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach(unlock);
    });
}
