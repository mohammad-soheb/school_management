{{-- Fields of the add/edit section forms. Receives $section (or null) and $teacherOptions (id => name). --}}
@php $key = $section?->id ?? 'new'; @endphp
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="name" label="Section name" :value="$section?->name" required maxlength="20" placeholder="A" :id="'section-name-'.$key" />
    <x-form.input name="capacity" label="Capacity" type="number" min="1" max="500" :value="$section?->capacity" :id="'section-capacity-'.$key" hint="Optional seat limit." />
</div>
<x-form.select name="class_teacher_id" label="Class teacher" :options="$teacherOptions" :selected="$section?->class_teacher_id"
               placeholder="Not chosen yet" :id="'section-teacher-'.$key"
               :hint="$teacherOptions === [] ? 'No active staff yet. Add staff members first.' : 'Active staff members only.'" />
<x-form.input name="sort_order" label="Order" type="number" min="0" max="999" :value="$section?->sort_order ?? 0" :id="'section-order-'.$key" />
