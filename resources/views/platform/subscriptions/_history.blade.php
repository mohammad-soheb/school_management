{{-- Renewal history timeline. Receives $events (RenewalEvent, with newPlan/previousPlan loaded; actor optional). --}}
@php $showActor = $showActor ?? true; @endphp
@if ($events->isEmpty())
    <x-ui.empty icon="clock" title="No history yet" description="Plan assignments, renewals and changes will appear here." />
@else
    <ol class="divide-y divide-line">
        @foreach ($events as $event)
            <li class="flex gap-3 px-4 py-3 sm:px-5">
                <span @class([
                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                    'bg-success/10 text-success-ink' => $event->type->tone() === 'success',
                    'bg-brand-500/10 text-brand-ink' => $event->type->tone() === 'brand',
                    'bg-info/10 text-info-ink' => $event->type->tone() === 'info',
                    'bg-danger/10 text-danger-ink' => $event->type->tone() === 'danger',
                ])>
                    <x-ui.icon :name="$event->type->icon()" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-ink">
                        {{ $event->type->label() }}
                        @if ($event->type === \App\Enums\Subscriptions\RenewalEventType::PlanChanged)
                            <span class="font-normal text-ink-soft">{{ $event->previousPlan?->name ?? '—' }} → {{ $event->newPlan?->name ?? '—' }}</span>
                        @elseif ($event->type === \App\Enums\Subscriptions\RenewalEventType::Activated)
                            <span class="font-normal text-ink-soft">{{ $event->newPlan?->name }}</span>
                        @endif
                    </p>
                    @if ($event->new_expires_on)
                        <p class="text-sm text-ink-soft">
                            @if ($event->previous_expires_on)
                                Expiry {{ $event->previous_expires_on->format('d M Y') }} → <span class="font-medium text-ink">{{ $event->new_expires_on->format('d M Y') }}</span>
                            @else
                                Expires {{ $event->new_expires_on->format('d M Y') }}
                            @endif
                        </p>
                    @endif
                    @if ($event->notes)
                        <p class="mt-0.5 text-sm text-ink-soft">“{{ $event->notes }}”</p>
                    @endif
                    <p class="mt-0.5 text-xs text-ink-faint">
                        {{ $event->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}
                        @if ($showActor && $event->relationLoaded('actor') && $event->actor)
                            · {{ $event->actor->name }}
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
