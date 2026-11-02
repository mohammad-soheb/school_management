// ============================================================================
// Copy to clipboard
// ----------------------------------------------------------------------------
//   <button type="button" x-data="copy('ADM-1042')" @click="copy()">
//       <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
//   </button>
// (or <x-ui.copy text="..."/>). Works on plain-HTTP dev servers and old Android
// browsers through the execCommand fallback.
// ============================================================================

async function writeText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);

        return;
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();

    try {
        if (!document.execCommand('copy')) {
            throw new Error('copy command rejected');
        }
    } finally {
        document.body.removeChild(area);
    }
}

export function registerCopy(Alpine) {
    Alpine.data('copy', (text = '') => ({
        copied: false,
        failed: false,
        timer: null,

        async copy() {
            try {
                await writeText(String(text));
                this.copied = true;
                this.failed = false;
                window.toast?.('Copied to clipboard', 'success');
            } catch {
                this.failed = true;
                window.toast?.('Could not copy. Select the text and copy it manually.', 'warning');
            }

            window.clearTimeout(this.timer);
            this.timer = window.setTimeout(() => {
                this.copied = false;
                this.failed = false;
            }, 2000);
        },
    }));
}
