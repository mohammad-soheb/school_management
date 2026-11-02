# Module scripts

Every `resources/js/modules/*.js` file is picked up automatically by
`resources/js/app.js` (`import.meta.glob('./modules/*.js', { eager: true })`),
bundled into the one `app.js` entry and executed **before** `Alpine.start()`.
You never edit `app.js`, `vite.config.js` or a Blade layout to add a script.

## Convention

* One file per module, named after it: `attendance.js`, `fees.js`, `exams.js` ...
  (the same names as `resources/scss/modules/_<module>.scss`).
* Register behaviour with `Alpine.data(...)` and use it from Blade with `x-data`.
* Prefix component names with the module (`attendanceGrid`, `feeEntry`) so two modules never clash.
* Keep it small. Page markup stays in Blade; only interaction lives here.
* No inline `<script>` in views (CSP). Pass data in as arguments: `x-data="feeEntry(@js($config))"`.
* Use the standard Alpine build (inline expressions are fine). Do not import Alpine plugins here;
  `focus` (`x-trap`, `$focus`) and `collapse` (`x-collapse`) are already registered.
* Large third-party libraries: load them lazily with a dynamic `import()` inside the component, the
  way `components/chart.js` loads Chart.js, so pages that do not need them do not download them.

## Template

```js
// resources/js/modules/attendance.js
import Alpine from 'alpinejs';

Alpine.data('attendanceGrid', (config = {}) => ({
    rows: config.rows ?? [],
    dirty: false,

    markAllPresent() {
        this.rows.forEach((row) => (row.status = 'present'));
        this.dirty = true;
    },
}));
```

```blade
<div x-data="attendanceGrid(@js(['rows' => $rows]))">
    <x-ui.button variant="secondary" @click="markAllPresent()">Mark all present</x-ui.button>
    ...
</div>
```

## Helpers you can rely on

| Helper | What it does |
|---|---|
| `window.toast(message, tone = 'info', title = '')` | Shows a toast (`success`, `info`, `warning`, `danger`). |
| `$store.theme.dark` | `true` in dark mode. The `theme-changed` window event fires when it flips. |
| `x-confirm="'Message'"` | Native confirm before a click / submit continues. |
| `x-trap`, `$focus`, `x-collapse` | Alpine focus + collapse plugins. |
| `data-lock="false"` on a `<form>` | Opts out of the double-submit lock (e.g. file downloads). |

See `docs/UI_GUIDE.md` for the full component reference.
