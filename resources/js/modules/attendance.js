// ============================================================================
// Attendance marking grid (resources/views/school/attendance/sheet.blade.php)
// ----------------------------------------------------------------------------
// The page is plain HTML radios (works without JS); this component adds:
//   * live counts per status, "Mark all present", a quick filter,
//   * correction mode on a submitted sheet (radios unlock, reason appears, changed rows highlight),
//   * a leave-page warning while there are unsaved changes.
// x-data="attendanceGrid({ statuses: {id: 'present'}, editing: true })"
// ============================================================================

import Alpine from 'alpinejs';

Alpine.data('attendanceGrid', (config = {}) => ({
    statuses: { ...(config.statuses ?? {}) },
    original: { ...(config.statuses ?? {}) },
    editing: Boolean(config.editing ?? true),
    filter: '',
    submitting: false,

    init() {
        this._beforeUnload = (event) => {
            if (this.dirty && !this.submitting) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this._beforeUnload);
    },

    destroy() {
        window.removeEventListener('beforeunload', this._beforeUnload);
    },

    count(status) {
        return Object.values(this.statuses).filter((value) => value === status).length;
    },

    get total() {
        return Object.keys(this.statuses).length;
    },

    get changed() {
        return Object.keys(this.statuses).filter((id) => this.statuses[id] !== this.original[id]).length;
    },

    // Marking a new register is always "dirty" until it is submitted; a correction only once something changed.
    get dirty() {
        return this.editing && (config.correction ? this.changed > 0 : this.total > 0);
    },

    isChanged(id) {
        return config.correction === true && this.statuses[id] !== this.original[id];
    },

    markAll(status = 'present') {
        Object.keys(this.statuses).forEach((id) => {
            this.statuses[id] = status;
        });
    },

    startCorrection() {
        this.editing = true;
        this.$nextTick(() => this.$refs.reason?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    },

    cancelCorrection() {
        this.statuses = { ...this.original };
        this.editing = false;
    },

    matches(name) {
        const needle = this.filter.trim().toLowerCase();

        return needle === '' || String(name).toLowerCase().includes(needle);
    },

    onSubmit() {
        this.submitting = true;
    },
}));
