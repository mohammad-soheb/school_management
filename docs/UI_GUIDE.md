# UI guide: components, page skeletons, conventions

The reference you code views against. Every component also documents its API in a header comment
(`resources/views/components/{ui,form,layouts}/*.blade.php`): when this guide and a header disagree, the header wins (and tell the lead).
Live examples of everything below: the **UI gallery** at `/_ui` (local env only, `routes/dev.php`), plus `/_ui/portal`, `/_ui/print`, `/_ui/auth/login`, `/_ui/errors/404`, and `/_ui?invalid=1` for forms in their failed-validation state.

Stack: Blade components + Tailwind CSS 3 (via SCSS) + Alpine.js 3 (`focus`, `collapse` plugins) + Inter Variable. Icons: Heroicons via blade-icons.

---

## 1. Ground rules

1. **Use the components.** `<x-ui.*>`, `<x-form.*>`, `<x-layouts.*>`. Don't hand-roll a button, a modal or an input.
2. **Token colours only**, so dark mode works with no `dark:` variants:

   | Purpose | Classes |
   |---|---|
   | page background | `bg-canvas` (the layouts set it) |
   | cards, tables, panels | `bg-surface`, `bg-surface-muted` (header rows, wells), `bg-surface-raised` (popovers) |
   | text | `text-ink` (primary), `text-ink-soft` (secondary), `text-ink-faint` (hints, meta) |
   | borders | `border-line` (hairline; plain `border` already uses it), `border-line-strong` (inputs) |
   | brand | `bg-brand-600`, `bg-brand-500/10`, `text-brand-ink` (brand text on a surface, readable in both themes) |
   | status | `text-success-ink`, `bg-success/10`, same for `warning`, `danger`, `info` |

   Never use `bg-white`, `text-gray-*`, `bg-slate-*` in app pages. Exceptions: print documents (always light) and a QR code tile.
3. **Tones** (badge, alert, stat, progress, toast, enum `tone()`): `neutral | brand | success | warning | danger | info`.
   success = done / paid / present / active; warning = needs attention / partial / late; danger = failed / overdue / absent / destructive;
   info = neutral news / scheduled / on leave; brand = emphasis / current; neutral = draft / inactive / left.
4. **Phones first.** 44px touch targets come for free from the components. Tables scroll inside their wrapper, never the page.
5. **No inline `<script>`** (CSP). Interaction goes in Alpine (`x-data`) or in `resources/js/modules/<module>.js`.
6. **Security is server-side.** Hiding a button with `@can` is UX; the route/request/policy still authorises.

---

## 2. Layouts

| Layout | Use | Props |
|---|---|---|
| `<x-layouts.app :title="…">` | every school-area page (`/app/...`) | `title` (required), `menu` / `user` (overrides, tests and the gallery only) |
| `<x-layouts.platform :title="…">` | Super-admin backoffice (`/platform/...`) | same as app |
| `<x-layouts.portal :title="…" :children="$children">` | parent portal (`/parent/...`) | `children`: `[['id', 'name', 'class', 'active' => bool], …]`, switching posts `student_id` to `parent.children.switch` |
| `<x-layouts.guest :title="…" heading="…" subheading="…">` | sign-in, password reset, invitations | `inline-status`: page prints `session('status')` itself, so it is not toasted |
| `<x-layouts.print :title="…" paper="a4">` | receipts, report cards, certificates, ID cards | `paper`: `a4` (default) \| `a5` \| `a6` \| `card` (85.6 x 54 mm); `back` URL |

The app shell builds itself: sidebar from `resources/menus/school/*.php` (group labels, icons, active pill, badges; items without a route or permission are hidden),
school switcher (when the user has more than one school), academic-year badge, `/` shortcut to the student search (when `school.students.index` exists), theme toggle, account menu.
Below `lg` the sidebar is an off-canvas drawer (focus-trapped, Esc / backdrop closes it).

Every layout except print renders the **toast stack** (flash messages), so pages never need to.
Pages may `@push('styles')` / `@push('scripts')` (an external `<script src>` only; no inline scripts).

**Print:** `?autoprint=1` opens the print dialog on load. `.no-print` hides an element on paper; `.print-break-after`, `.print-avoid-break` control page breaks.
Documents are always light; inside them, plain Tailwind greys (`text-slate-600`, `border-slate-300`) are fine. Module print CSS goes in your `resources/scss/modules/_<module>.scss`.

---

## 3. `ui` components

| Component | Props (defaults) | Notes |
|---|---|---|
| `x-ui.page-header` | `title`, `description`, `breadcrumbs` (`[['label', 'url'?], …]`) · slots: default (badges/meta under the title), `actions` | last crumb / crumb without url = current page |
| `x-ui.card` | `title`, `description`, `padding` (true) · slots: `actions`, `footer` | `:padding="false"` for tables/lists that touch the edges. `class="card-interactive"` on a clickable card |
| `x-ui.stat` | `label`, `value`, `icon`, `tone` (brand), `hint`, `delta` ("+4.2%" green, "-3%" red, "0%" flat), `href` | |
| `x-ui.badge` | `tone` (neutral), `dot` | |
| `x-ui.alert` | `tone` (info), `title`, `dismissible` | danger/warning get `role="alert"` |
| `x-ui.button` | `variant` primary\|secondary\|ghost\|danger\|success, `size` sm\|md\|lg, `href` (renders `<a>`), `type` (button), `icon`, `icon-right`, `block` | icon-only: `class="btn-icon" aria-label="…"` |
| `x-ui.table` | `caption` (accessible name, visually hidden), `dense`, `tall` (max height + sticky header) · slots: `head` (`<th>` cells only), default (`<tr>` rows), `foot` | cell helpers: `class="num"` (right-aligned tabular), `class="col-actions"` |
| `x-ui.th` | `column` (makes it sortable via `?sort=&dir=`), `label` (or slot) | keeps other query params, resets `?page`. **Whitelist sortable columns in your service** |
| `x-ui.empty` | `icon` (inbox), `title` ("Nothing here yet"), `description` · default slot = action buttons | |
| `x-ui.modal` | `name` (required, unique), `title`, `description`, `max-width` sm\|md\|lg\|xl, `show`, `closeable` (true) · slot `footer` | teleported to `<body>`; bottom sheet on phones; focus trapped |
| `x-ui.confirm` | `action` (required), `method` (DELETE), `title`, `message`, `confirm` ("Confirm"), `cancel`, `tone` (danger) · default slot = the trigger | renders its own form with CSRF + method spoofing; don't nest inside a `<form>` |
| `x-ui.dropdown` | `align` right\|left, `width` 48\|56\|64\|72\|80, `label` · slot `trigger` | keyboard: arrows, Home/End, Esc |
| `x-ui.dropdown-link` | `href`, `icon`, `danger`, `as` (a\|button) | `as="button"` is a submit button: put it in a `<form>`. Separator: `<hr class="dropdown-divider">`, heading: `<p class="dropdown-label">` |
| `x-ui.tabs` | `tabs` (`[['label', 'url', 'active', 'badge'?, 'icon'?], …]`), `label`, `pill` | link tabs: each tab is its own URL; the controller decides `active` |
| `x-ui.avatar` | `name`, `src`, `size` xs\|sm\|md\|lg\|xl | initials on a colour derived from the name; `aria-hidden="true"` when the name is printed beside it |
| `x-ui.progress` | `value`, `max` (100), `tone` (brand), `label`, `show-value`, `large` | |
| `x-ui.chart` | `type` bar\|line\|doughnut\|pie, `labels`, `datasets` (Chart.js datasets), `options`, `height` (280), `label` | Chart.js loads lazily; colours follow the theme; hidden data table for screen readers |
| `x-ui.dl` / `x-ui.dl-item` | `dl`: `grid` · `dl-item`: `label` | empty value prints an em dash |
| `x-ui.filters` | `reset` (URL), `placeholder`, `search` (true, field `q`), `action`, `search-label` | GET form; carries `sort`/`dir` |
| `x-ui.toasts` | `except` | rendered by the layouts; you don't use it |
| `x-ui.copy` | `text` (required), `label` ("Copy"), `variant` (secondary), `size` (sm) | |
| `x-ui.icon` | `name` (heroicon name without prefix), `variant` o\|s\|m\|c | unknown name falls back to `squares-2x2`; decorative unless you pass `aria-label` |
| `x-ui.skeleton` | `variant` text\|avatar\|block, `lines` (1) | put `aria-busy="true"` on the container |

```blade
<x-ui.stat label="Collected today" :value="Money::format($todayPaise)" icon="banknotes" tone="success" hint="42 receipts" :href="route('school.fees.collect')" />

<x-ui.badge :tone="$payment->status->tone()" dot>{{ $payment->status->label() }}</x-ui.badge>

<x-ui.button :href="route('school.students.create')" icon="plus">Admit student</x-ui.button>
<x-ui.button variant="ghost" icon="trash" class="btn-icon" aria-label="Remove {{ $row->name }}" />

<x-ui.alert tone="warning" title="Three instalments are overdue">Reminders have not been sent yet.</x-ui.alert>

<x-ui.dropdown align="right" width="48" label="Actions for {{ $student->name }}">
    <x-slot:trigger>
        <button type="button" class="btn btn-ghost btn-sm btn-icon" aria-label="Actions for {{ $student->name }}"><x-ui.icon name="ellipsis-horizontal" /></button>
    </x-slot:trigger>
    <x-ui.dropdown-link :href="route('school.students.edit', $student)" icon="pencil-square">Edit</x-ui.dropdown-link>
</x-ui.dropdown>
```

### Modals and confirm

```blade
{{-- open from anywhere on the page --}}
<x-ui.button icon="user-plus" @click="$dispatch('open-modal', 'invite-user')">Invite</x-ui.button>

<x-ui.modal name="invite-user" title="Invite a user" description="They will receive an email." max-width="lg" :show="$errors->invite->any()">
    <x-form.form id="invite-form" :action="route('school.access.members.invite')" class="space-y-5">
        <x-form.input name="email" type="email" label="Email" autofocus required />
    </x-form.form>
    <x-slot:footer>
        <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'invite-user')">Cancel</x-ui.button>
        <x-ui.button type="submit" form="invite-form">Send invite</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

{{-- destructive action: the slot is the trigger; Cancel has focus, Enter never confirms by accident --}}
<x-ui.confirm :action="route('school.students.leave', $student)" method="PATCH"
              title="Mark {{ $student->name }} as left?" message="Their history is kept." confirm="Mark as left">
    <x-ui.button variant="danger" icon="archive-box">Mark as left</x-ui.button>
</x-ui.confirm>
```

Quick native confirm for small cases: `<button type="submit" x-confirm="'Remove this row?'">`.

---

## 4. `form` components

Shared behaviour of every control: stable id `f-{name}` (brackets sanitised: `items[0][name]` -> `f-items-0-name`), `<label for>`,
**`old()` wins over `value`** after a failed submit, first validation error under the control with `aria-invalid` + `aria-describedby`,
the hint is hidden while an error shows. `class` goes to the field wrapper (grid placement, e.g. `md:col-span-2`); other attributes
(`placeholder`, `autocomplete`, `min`, `x-model` …) go to the control; `input-class` adds classes to the control.

| Component | Props (defaults) | Notes |
|---|---|---|
| `x-form.form` | `action`, `method` POST\|PUT\|PATCH\|DELETE\|GET, `files` | CSRF + method spoofing; double-submit lock (opt out `data-lock="false"`) |
| `x-form.field` | `label`, `name`, `hint`, `required`, `for` | wraps any custom control |
| `x-form.input` | `name`, `label`, `type` (text), `value`, `hint`, `required`, `prefix`, `suffix`, `input-class` | passwords never refilled |
| `x-form.select` | `name`, `label`, `options` (value => label), `selected`, `placeholder` (adds `value=""`), `hint`, `required` | `multiple` + `name="x[]"`; raw `<option>`/`<optgroup>` in the slot |
| `x-form.textarea` | `name`, `label`, `value`, `hint`, `required`, `rows` (4) | |
| `x-form.checkbox` | `name`, `label`, `value` (1), `checked`, `hint` | unticked submits nothing: read with `$request->boolean()`; arrays: `name="roles[]"` |
| `x-form.radio` | `name`, `value`, `label`, `checked`, `hint` | put one `<x-form.error name="…"/>` after the group |
| `x-form.toggle` | as checkbox | `role="switch"` |
| `x-form.date` | as input | native date picker, value `Y-m-d` |
| `x-form.money` | `name`, `label`, `value` (rupees string), `paise` (int), `hint`, `required`, `prefix` (₹) | submits a plain decimal string; convert with `Money::parse()` |
| `x-form.file` | `name`, `label`, `accept`, `hint`, `multiple`, `required` | drop-zone; remember `:files="true"` on the form |
| `x-form.lookup` | `name`, `url`, `label`, `placeholder`, `value`, `display`, `sublabel`, `hint`, `required`, `min-chars` (2) | async search-select, see below |
| `x-form.password` | `name` (password), `label`, `hint`, `required` · slot `aside` | show/hide button |
| `x-form.error` | `name`, `id` | standalone message |
| `x-form.actions` | `sticky` | primary action LAST in markup |
| `x-form.idempotency` | `name` (idempotency_key) | hidden UUID, fresh per render |

### Create / edit form skeleton

```blade
<x-layouts.app :title="$student->exists ? 'Edit student' : 'Admit student'">
    <x-ui.page-header :title="…" :breadcrumbs="[['label' => 'Students', 'url' => route('school.students.index')], ['label' => '…']]" />

    <x-ui.card>
        <x-form.form :action="$student->exists ? route('school.students.update', $student) : route('school.students.store')"
                     :method="$student->exists ? 'PUT' : 'POST'" :files="true" class="space-y-8">
            <div class="form-section">
                <div>
                    <h3 class="form-section-title">Student</h3>
                    <p class="form-section-description">As on the admission register.</p>
                </div>
                <div class="form-grid">  {{-- 1 column on phones, 2 from md; form-grid-3 for 3 --}}
                    <x-form.input name="name" label="Full name" :value="$student->name" required />
                    <x-form.date name="date_of_birth" label="Date of birth" :value="$student->date_of_birth?->format('Y-m-d')" required />
                    <x-form.select name="section_id" label="Class" :options="$sections" :selected="$student->section_id" placeholder="Choose a class" required />
                    <x-form.textarea name="remarks" label="Remarks" :value="$student->remarks" class="md:col-span-2" />
                </div>
            </div>

            <x-form.actions sticky>
                <x-ui.button :href="route('school.students.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit">Save</x-ui.button>
            </x-form.actions>
        </x-form.form>
    </x-ui.card>
</x-layouts.app>
```

### Money and idempotency

Money is **integer paise** server-side. The field shows/submits rupees:

```blade
<x-form.form :action="route('school.fees.payments.store')">
    <x-form.idempotency />                                   {{-- service: unique (school_id, idempotency_key) --}}
    <x-form.money name="amount" label="Amount received" required />
    <x-form.money name="discount" label="Discount" :paise="$charge->discount_paise" />
    <x-form.actions><x-ui.button type="submit" icon="banknotes">Post payment</x-ui.button></x-form.actions>
</x-form.form>
```

Display money with `Money::format($paise)` / `@money($paise)` in a `class="num"` cell. The submit lock stops double clicks in the browser; the idempotency key makes a retry safe on the server. You need both.

### Lookup (async search-select)

```blade
<x-form.lookup name="student_id" label="Student" :url="route('school.students.lookup')"
               placeholder="Admission no. or name" :value="$student?->id" :display="$student?->name" required />
```

The endpoint gets `GET {url}?q=term` and returns `[{"id": 1, "label": "Aarav Sharma", "sublabel": "Adm. 1042 · Class 5-A"}]`
(scope it to the school and the user's permissions like any other read; cap the result count). The id is posted as `name`.
After a failed submit the old id is restored automatically; pass `display` / `sublabel` so the box shows the person.
Events bubble from the wrapper: `@lookup-selected="load($event.detail.id)"` (detail: `name, id, label, sublabel, item`) and `@lookup-cleared`.

---

## 5. Page skeletons

### Index: header, filters, table, pagination, empty state

```blade
<x-layouts.app title="Students">
    <x-ui.page-header title="Students" description="Everyone enrolled this year.">
        <x-slot:actions>
            @can('students.create')
                <x-ui.button :href="route('school.students.create')" icon="plus">Admit student</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filters :reset="route('school.students.index')" placeholder="Search name or admission no.">
        <x-form.select name="section_id" label="Class" placeholder="All classes" :options="$sections" :selected="request('section_id')" />
    </x-ui.filters>

    <x-ui.card :padding="false" class="mt-4">
        @if ($students->isEmpty())
            <x-ui.empty icon="user-group" :title="request()->hasAny(['q', 'section_id']) ? 'No students match your filters' : 'No students yet'">
                <x-ui.button :href="route('school.students.create')" icon="plus">Admit student</x-ui.button>
            </x-ui.empty>
        @else
            <x-ui.table caption="Students">
                <x-slot:head>
                    <x-ui.th column="admission_no">Adm. no.</x-ui.th>
                    <x-ui.th column="name" label="Student" />
                    <x-ui.th class="num">Balance</x-ui.th>
                    <th class="col-actions"><span class="sr-only">Actions</span></th>
                </x-slot:head>
                @foreach ($students as $student)
                    <tr>
                        <td class="tabular-nums text-ink-soft">{{ $student->admission_no }}</td>
                        <td><a href="{{ route('school.students.show', $student) }}" class="font-medium text-ink hover:text-brand-ink">{{ $student->name }}</a></td>
                        <td class="num">@money($student->balance_paise)</td>
                        <td class="col-actions"><x-ui.button :href="route('school.students.show', $student)" variant="ghost" size="sm">View</x-ui.button></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($students->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $students->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
```

Paginate in the service with `->paginate(25)->withQueryString()` so the filters survive. `->links()` renders our pager
("Showing x–y of z results", numbers with ellipsis, Previous/Next; "Page x of y" on phones); `->simplePaginate()` and `->cursorPaginate()` get a Previous/Next pager.

### Detail: header, tabs, description list

```blade
<x-layouts.app :title="$student->name">
    <x-ui.page-header :title="$student->name" :description="'Adm. '.$student->admission_no"
                      :breadcrumbs="[['label' => 'Students', 'url' => route('school.students.index')], ['label' => $student->name]]">
        <x-ui.badge :tone="$student->status->tone()" dot>{{ $student->status->label() }}</x-ui.badge>
        <x-slot:actions><x-ui.button :href="route('school.students.edit', $student)" variant="secondary" icon="pencil-square">Edit</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-6" label="Student sections" :tabs="[
        ['label' => 'Overview', 'url' => route('school.students.show', $student), 'active' => request()->routeIs('school.students.show')],
        ['label' => 'Fees', 'url' => route('school.fees.students.show', $student), 'active' => false],
    ]" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Details" class="lg:col-span-2">
            <x-ui.dl>
                <x-ui.dl-item label="Class">{{ $student->section?->name }}</x-ui.dl-item>
                <x-ui.dl-item label="Date of birth">{{ $student->date_of_birth?->format('d M Y') }}</x-ui.dl-item>
            </x-ui.dl>
        </x-ui.card>
        <x-ui.card title="Fees"><x-ui.dl grid>…</x-ui.dl></x-ui.card>
    </div>
</x-layouts.app>
```

### Print document

```blade
<x-layouts.print :title="'Receipt '.$payment->number" paper="a5" :back="route('school.fees.students.show', $payment->student)">
    <div class="receipt">…</div>   {{-- styles in resources/scss/modules/_fees.scss --}}
</x-layouts.print>
```

Link to it with `target="_blank"` (and `?autoprint=1` to open the print dialog straight away).

---

## 6. Flash messages and toasts

From a controller: `return redirect()->route(…)->with('status', 'Receipt R-1042 saved.');`

| Session key | Toast tone |
|---|---|
| `status`, `success` | success |
| `error` | danger |
| `warning` | warning |
| `info` | info |

A failed validation automatically adds "Please fix the highlighted fields" (danger). Messages that look like Fortify keys
(`password-updated`) are humanised. From JavaScript: `window.toast('Copied', 'success')` or dispatch a `toast` window event
(`{ tone, message, title }`).

---

## 7. Dark mode

`.dark` on `<html>` (set before first paint from the saved choice or the OS). Token classes flip automatically; alpha utilities
(`bg-brand-500/10`) work in both themes. Only add a `dark:` variant for something tokens can't express. Charts re-colour on
`theme-changed`. Print pages are always light. Check every new page in both themes (moon icon in the top bar).

---

## 8. Module styles and scripts

* **SCSS:** your file `resources/scss/modules/_<module>.scss` already exists and is forwarded by `modules/_index.scss`
  (setup, students, staff, attendance, fees, exams, comms, portal, reports, import, subscriptions, certificates, promotion, salary).
  Start it with `@use '../abstracts' as *;` and wrap rules in `@layer components { … }`. Prefix classes with the module (`.fees-receipt`).
  Use `@apply` with token classes. Mixins from `abstracts`: `bp($name)`, `coarse`, `reduced-motion`, `focus-ring($offset)`, `thin-scrollbar`.
* **JS:** add `resources/js/modules/<module>.js`; `app.js` bundles every file in that folder and runs it before `Alpine.start()`.
  Register with `Alpine.data('<module>Thing', (config) => ({ … }))`, pass data via `x-data="feeEntry(@js($config))"`. Lazy-load big libraries with dynamic `import()`.
  Details: `resources/js/modules/README.md`.
* Validate your SCSS without touching `public/build`: `npx vite build --outDir "$TEMP/vite-<module>" --emptyOutDir`. Only the lead/UI owner runs `npm run build`.
* Shared files (`resources/scss/{abstracts,base,components,layouts}`, `resources/js/{app.js,core,components}`, `resources/views/components/**`) are not yours; ask the lead for a change.

---

## 9. Accessibility checklist

* One `<h1>` per page (the page header). Card titles are `<h2>`.
* Every control has a label (the form components do it). Icon-only buttons need `aria-label`.
* Tables: give `<x-ui.table>` a `caption`; an actions column header gets `<span class="sr-only">Actions</span>`.
* Don't rely on colour alone: badges carry a word; charts carry a hidden data table.
* Status changes from scripts go through `window.toast()` (announced via `aria-live`).
* Keyboard: modals trap focus and restore it; dropdowns use arrow keys; `/` focuses the student search.
* Respect `prefers-reduced-motion` (the shared CSS already does); keep custom animations short and optional.
* Links that open a new tab (print views) should say so in the text or `aria-label`.

---

## 10. Gotchas

* A Blade slot closing tag directly followed by text (`</x-slot:actions>Body`) does not compile; leave whitespace or a newline.
* `<x-icon>` is taken by blade-icons; use `<x-ui.icon>`.
* `<x-ui.confirm>` renders a `<form>`: never put it inside another form.
* `x-ui.th` sorting only builds links; the service must whitelist `sort` columns and `dir`.
* Strict mode: eager-load what a view touches (`->with([...])`), or the page throws a lazy-loading exception.
