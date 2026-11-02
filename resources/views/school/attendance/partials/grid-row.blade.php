{{--
    One person on a marking grid: name + five status chips (native radios, big touch targets) + optional remark.
    Params: $key (statuses key), $field (input name prefix, e.g. "attendance[12]"), $badge (roll no / initials),
            $name, $sub (second line), $status (current value), $remark, $locked (render disabled until correction mode),
            $meta (optional small note, e.g. "Corrected by ...").
--}}
@php
    $statusOptions = \App\Enums\Attendance\AttendanceStatus::cases();
    $locked = $locked ?? false;
    $remarkId = 'remark-'.\Illuminate\Support\Str::slug($field);
@endphp
<li class="att-row" x-show="matches(@js($name.' '.$badge.' '.$sub))"
    :class="{ 'is-absent': statuses['{{ $key }}'] === 'absent', 'is-changed': isChanged('{{ $key }}') }"
    x-data="{ note: @js(filled($remark)) }">
    <div class="min-w-0 flex-1">
        <div class="att-student">
            <span class="att-roll">{{ $badge }}</span>
            <div class="min-w-0">
                <p class="truncate font-medium text-ink">{{ $name }}</p>
                @if (filled($sub))
                    <p class="truncate text-sm text-ink-faint">{{ $sub }}</p>
                @endif
            </div>
        </div>
        @if (filled($meta ?? null))
            <p class="att-remark">{{ $meta }}</p>
        @endif
        @if ($locked && filled($remark))
            <p class="att-remark" x-show="!editing"><x-ui.icon name="chat-bubble-bottom-center-text" class="inline h-4 w-4" /> {{ $remark }}</p>
        @endif
        <div class="mt-2" x-show="note && (editing || {{ $locked ? 'false' : 'true' }})" x-cloak>
            <label for="{{ $remarkId }}" class="sr-only">Remark for {{ $name }}</label>
            <input id="{{ $remarkId }}" type="text" name="{{ $field }}[remark]" value="{{ $remark }}" maxlength="255" class="input" placeholder="Remark (optional)"
                   @if ($locked) disabled x-bind:disabled="!editing" @endif>
        </div>
    </div>

    <div class="flex items-center gap-1.5">
        <fieldset class="att-chips">
            <legend class="sr-only">Status for {{ $name }}</legend>
            @foreach ($statusOptions as $option)
                <label class="att-chip is-{{ $option->value }}" title="{{ $option->label() }}">
                    <input type="radio" name="{{ $field }}[status]" value="{{ $option->value }}" x-model="statuses['{{ $key }}']"
                           @checked($status === $option->value) @if ($locked) disabled x-bind:disabled="!editing" @endif
                           aria-label="{{ $option->label() }}">
                    <span class="att-chip-face" aria-hidden="true"><span class="sm:hidden">{{ $option->code() }}</span><span class="hidden sm:inline">{{ $option->label() }}</span></span>
                </label>
            @endforeach
        </fieldset>
        <button type="button" class="btn btn-ghost btn-sm btn-icon min-h-touch min-w-touch" x-show="{{ $locked ? 'editing' : 'true' }}" @click="note = !note"
                :aria-expanded="note.toString()" aria-controls="{{ $remarkId }}" aria-label="Add a remark for {{ $name }}" title="Remark">
            <x-ui.icon name="chat-bubble-bottom-center-text" />
        </button>
    </div>
</li>
