@php
    $user = auth()->user();
    $context = app(\App\Support\Tenancy\TenantContext::class);
    $school = $context->school();
    $year = $context->academicYearLabel();
    $hour = (int) local_now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $shortcuts = collect(app(\App\Support\Navigation\Menu::class)->build('school'))
        ->flatMap(fn ($group) => collect($group['items'])->map(fn ($item) => $item + ['group' => $group['label']]))
        ->reject(fn ($item) => $item['label'] === 'Dashboard');
@endphp
<x-layouts.app title="Dashboard">
    <x-ui.page-header
        :title="$greeting.', '.explode(' ', $user->name)[0]"
        :description="$school->name.($year ? ' · Academic year '.$year : '')" />

    {{-- Drop-in widgets: a module adds resources/views/school/dashboard-widgets/NN-name.blade.php (receives $user, $school). --}}
    <div class="space-y-6">
        @foreach (glob(resource_path('views/school/dashboard-widgets/*.blade.php')) ?: [] as $widget)
            @include('school.dashboard-widgets.'.basename($widget, '.blade.php'), ['user' => $user, 'school' => $school])
        @endforeach

        @if ($shortcuts->isNotEmpty())
            <section aria-labelledby="shortcuts-title">
                <h2 id="shortcuts-title" class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-faint">Shortcuts</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($shortcuts as $item)
                        <a href="{{ $item['url'] }}" class="card card-interactive group flex items-center gap-4 p-4">
                            <span class="stat-icon stat-icon-brand">
                                <x-ui.icon :name="$item['icon']" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $item['label'] }}</span>
                                <span class="block truncate text-sm text-ink-faint">{{ $item['group'] }}</span>
                            </span>
                            <x-ui.icon name="arrow-right" variant="m" class="h-4 w-4 shrink-0 text-ink-faint opacity-0 transition duration-150 group-hover:translate-x-0.5 group-hover:opacity-100 group-focus-visible:opacity-100" />
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <x-ui.card>
                <x-ui.empty icon="squares-2x2" title="Nothing to open yet" description="Your role does not include any school screens. Ask your administrator to review your permissions." />
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
