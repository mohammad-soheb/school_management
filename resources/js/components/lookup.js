// ============================================================================
// Lookup: accessible async search-select (ARIA 1.2 combobox pattern)
// ----------------------------------------------------------------------------
// Use <x-form.lookup name="student_id" url="..."> in Blade. The endpoint gets
//   GET {url}?q=term   (same origin, JSON, cookies)
// and answers  [{ "id": 1, "label": "Aarav Sharma", "sublabel": "Adm. 1042 · Class 5-A" }, ...]
//
// The chosen id is submitted through a hidden input named `name`. Events (bubble):
//   lookup-selected  detail: { name, id, label, sublabel, item }
//   lookup-cleared   detail: { name }
// Keyboard: type to search, ArrowUp/Down to move, Enter to choose, Esc to close,
// Backspace on an empty box does nothing, the "x" button clears the selection.
// ============================================================================

export function registerLookup(Alpine) {
    Alpine.data('lookup', (config = {}) => ({
        // config
        name: config.name ?? '',
        url: config.url ?? '',
        uid: config.uid ?? `lookup-${Math.random().toString(36).slice(2, 8)}`,
        minChars: config.minChars ?? 2,
        delay: config.delay ?? 250,

        // selection
        id: config.value ?? '',
        label: config.display ?? '',
        sublabel: config.sublabel ?? '',

        // search state
        query: '',
        results: [],
        open: false,
        loading: false,
        failed: false,
        searched: false,
        active: -1,
        timer: null,
        controller: null,

        get hasValue() {
            return this.id !== '' && this.id !== null && this.id !== undefined;
        },

        get shownLabel() {
            return this.label || `#${this.id}`;
        },

        get listId() {
            return `${this.uid}-list`;
        },

        get status() {
            if (this.loading) {
                return 'Searching…';
            }

            if (this.failed) {
                return 'Search failed. Check your connection and try again.';
            }

            if (this.open && this.searched) {
                return this.results.length === 0 ? 'No results found.' : `${this.results.length} result${this.results.length === 1 ? '' : 's'} available. Use the up and down arrow keys to review.`;
            }

            return '';
        },

        optionId(index) {
            return `${this.uid}-option-${index}`;
        },

        activeId() {
            return this.open && this.active >= 0 ? this.optionId(this.active) : null;
        },

        onInput() {
            window.clearTimeout(this.timer);
            this.active = -1;

            const term = this.query.trim();

            if (term.length < this.minChars) {
                this.controller?.abort();
                this.results = [];
                this.open = false;
                this.loading = false;
                this.searched = false;
                this.failed = false;

                return;
            }

            this.timer = window.setTimeout(() => this.search(term), this.delay);
        },

        async search(term) {
            this.controller?.abort();
            const controller = new AbortController();
            this.controller = controller;
            this.loading = true;
            this.failed = false;
            this.open = true;

            try {
                const endpoint = new URL(this.url, window.location.origin);

                // Never send the session cookie to another origin.
                if (endpoint.origin !== window.location.origin) {
                    throw new Error('Cross-origin lookup URL');
                }

                endpoint.searchParams.set('q', term);

                const response = await fetch(endpoint, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                this.results = Array.isArray(data) ? data : (data.data ?? []);
                this.active = this.results.length > 0 ? 0 : -1;
                this.searched = true;
            } catch (error) {
                if (error.name === 'AbortError') {
                    return; // superseded by a newer request
                }

                this.results = [];
                this.failed = true;
                this.searched = true;
            } finally {
                if (this.controller === controller) {
                    this.loading = false;
                }
            }
        },

        move(delta) {
            if (!this.open) {
                if (this.results.length > 0) {
                    this.open = true;
                }

                return;
            }

            if (this.results.length === 0) {
                return;
            }

            this.active = (this.active + delta + this.results.length) % this.results.length;
            this.$nextTick(() => document.getElementById(this.optionId(this.active))?.scrollIntoView({ block: 'nearest' }));
        },

        edge(last) {
            if (this.open && this.results.length > 0) {
                this.active = last ? this.results.length - 1 : 0;
            }
        },

        enter(event) {
            if (this.open && this.active >= 0 && this.results[this.active]) {
                event.preventDefault(); // do not submit the surrounding form
                this.choose(this.results[this.active]);
            }
        },

        choose(item) {
            this.id = item.id;
            this.label = item.label ?? '';
            this.sublabel = item.sublabel ?? '';
            this.query = '';
            this.results = [];
            this.open = false;
            this.searched = false;
            this.active = -1;

            this.$dispatch('lookup-selected', { name: this.name, id: item.id, label: this.label, sublabel: this.sublabel, item });
            // The hidden input is now bound to `id`; tell listeners (and Alpine) the form value changed.
            this.$nextTick(() => this.$refs.hidden?.dispatchEvent(new Event('change', { bubbles: true })));
        },

        clear() {
            this.id = '';
            this.label = '';
            this.sublabel = '';
            this.query = '';
            this.results = [];

            this.$dispatch('lookup-cleared', { name: this.name });
            this.$nextTick(() => {
                this.$refs.hidden?.dispatchEvent(new Event('change', { bubbles: true }));
                this.$refs.input?.focus();
            });
        },

        close() {
            this.open = false;
            this.active = -1;
        },
    }));
}
