// ============================================================================
// School ERP front-end entry.
// Docs: docs/UI_GUIDE.md  |  module scripts: resources/js/modules/README.md
// ============================================================================

import '@fontsource-variable/inter';

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import { registerTheme } from './core/theme';
import { registerSubmitLock } from './core/submit-lock';
import { registerToasts } from './core/toasts';
import { registerChart } from './components/chart';
import { registerLookup } from './components/lookup';
import { registerConfirm } from './components/confirm';
import { registerCopy } from './components/copy';
import { registerFileInput } from './components/file-input';
import { registerModal } from './components/modal';
import { registerDropdown } from './components/dropdown';

window.Alpine = Alpine;

Alpine.plugin(focus); // x-trap, $focus
Alpine.plugin(collapse); // x-collapse

// ---- Core behaviour -----------------------------------------------------------
registerTheme(Alpine); // $store.theme (dark mode)
registerSubmitLock(); // double-submit protection for every <form>
registerToasts(Alpine); // toast stack + window.toast()

// ---- Reusable Alpine components -------------------------------------------------
registerChart(Alpine); // x-data="chart({...})"  (Chart.js is loaded lazily)
registerLookup(Alpine); // x-data="lookup({...})" async search-select
registerConfirm(Alpine); // x-data="confirm({ name })" + x-confirm="'Message'"
registerCopy(Alpine); // x-data="copy('text')"
registerFileInput(Alpine); // x-data="fileInput({...})" drop-zone
registerModal(Alpine); // x-data="modal({ name })"  (<x-ui.modal>)
registerDropdown(Alpine); // x-data="dropdown"       (<x-ui.dropdown>)

// ---- Module scripts ------------------------------------------------------------------
// Every resources/js/modules/*.js file is bundled and executed here, BEFORE Alpine starts, so a
// module can `import Alpine from 'alpinejs'; Alpine.data('name', () => ({...}))`.
import.meta.glob('./modules/*.js', { eager: true });

Alpine.start();
