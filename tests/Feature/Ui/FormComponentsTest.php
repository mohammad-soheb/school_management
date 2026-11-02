<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\RendersComponents;
use Tests\TestCase;

/**
 * The <x-form.*> components: ids, labels, old input after a failed submit, error wiring (aria-invalid /
 * aria-describedby) and the special controls (money, lookup, idempotency, file, password).
 */
class FormComponentsTest extends TestCase
{
    use RendersComponents;

    public function test_form_adds_csrf_and_spoofs_the_method(): void
    {
        $this->withRequestSession();

        $this->blade('<x-form.form action="/students/1" method="PUT" :files="true">x</x-form.form>')
            ->assertSee('method="POST"', false)
            ->assertSee('action="/students/1"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PUT"', false);

        $this->blade('<x-form.form action="/search" method="GET">x</x-form.form>')
            ->assertSee('method="GET"', false)
            ->assertDontSee('name="_token"', false);
    }

    public function test_input_renders_label_hint_value_and_affixes(): void
    {
        $this->withRequestSession();

        $this->blade('<x-form.input name="email" type="email" label="Email" value="a@example.test" hint="We never share it." required placeholder="you@school" class="md:col-span-2" />')
            ->assertSee('<label for="f-email" class="label label-required">Email</label>', false)
            ->assertSee('id="f-email"', false)
            ->assertSee('value="a@example.test"', false)
            ->assertSee('aria-describedby="f-email-hint"', false)
            ->assertSee('placeholder="you@school"', false)
            ->assertSee('class="field md:col-span-2"', false)
            ->assertSee('We never share it.');

        $this->blade('<x-form.input name="items[0][weight]" label="Weight" suffix="kg" />')
            ->assertSee('id="f-items-0-weight"', false)
            ->assertSee('<span class="input-affix">kg</span>', false);
    }

    public function test_failed_submit_restores_old_input_and_wires_errors(): void
    {
        $this->withFailedSubmit(
            ['email' => 'bad@', 'class_id' => '2', 'remarks' => 'Typed again', 'gender' => 'male', 'uses_bus' => '1', 'password' => 'secret'],
            ['email' => 'Enter a valid email address.', 'gender' => 'Choose one.'],
        );

        $this->blade('<x-form.input name="email" label="Email" value="saved@example.test" hint="Hidden on error" />')
            ->assertSee('value="bad@"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="f-email-error"', false)
            ->assertSee('<p id="f-email-error" class="field-error">', false)
            ->assertSee('Enter a valid email address.')
            ->assertDontSee('Hidden on error');

        $this->blade('<x-form.select name="class_id" :options="[1 => \'Class 4\', 2 => \'Class 5\']" selected="1" />')
            ->assertSee('<option value="2" selected>Class 5</option>', false);

        $this->blade('<x-form.textarea name="remarks" value="Saved" />')->assertSee('Typed again');

        $this->blade('<x-form.radio name="gender" value="male" label="Male" />')->assertSee('checked', false)->assertSee('aria-describedby="f-gender-error"', false);
        $this->blade('<x-form.radio name="gender" value="female" label="Female" checked />')->assertDontSee('checked', false);

        // A checkbox that was not ticked on the failed submit stays unticked even when `checked` says otherwise.
        $this->blade('<x-form.checkbox name="sms" label="SMS" checked />')->assertDontSee('checked', false);
        $this->blade('<x-form.checkbox name="uses_bus" label="Bus" />')->assertSee('checked', false);

        // Passwords are never re-filled.
        $this->blade('<x-form.input name="password" type="password" label="Password" />')->assertDontSee('secret');
        $this->blade('<x-form.password name="password" />')->assertDontSee('secret')->assertSee('aria-label="Show password"', false);
    }

    public function test_select_supports_placeholder_and_multiple(): void
    {
        $this->withRequestSession();

        $this->blade('<x-form.select name="roles[]" label="Roles" :options="[\'a\' => \'Admin\', \'t\' => \'Teacher\', \'p\' => \'Parent\']" :selected="[\'a\', \'t\']" multiple placeholder="Pick" />')
            ->assertSee('name="roles[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('<option value="">Pick</option>', false)
            ->assertSee('<option value="a" selected>Admin</option>', false)
            ->assertSee('<option value="t" selected>Teacher</option>', false)
            ->assertSee('<option value="p" >Parent</option>', false);
    }

    public function test_checkbox_toggle_date_and_file(): void
    {
        $this->withRequestSession();

        $this->blade('<x-form.checkbox name="send_sms" label="Send SMS" hint="Charges apply." checked />')
            ->assertSee('type="checkbox"', false)->assertSee('checked', false)->assertSee('Charges apply.');
        $this->blade('<x-form.toggle name="is_active" label="Active" />')->assertSee('role="switch"', false)->assertSee('class="toggle"', false);
        $this->blade('<x-form.date name="date_of_birth" label="Date of birth" value="2015-06-14" />')
            ->assertSee('type="date"', false)->assertSee('value="2015-06-14"', false);
        $this->blade('<x-form.file name="documents" label="Documents" multiple accept="application/pdf" />')
            ->assertSee('name="documents[]"', false)->assertSee('accept="application/pdf"', false)->assertSee('Choose files');
    }

    public function test_money_takes_rupees_or_paise_and_shows_the_rupee_prefix(): void
    {
        $this->withRequestSession();

        $this->blade('<x-form.money name="amount" label="Amount" :paise="125050" required />')
            ->assertSee('value="1250.50"', false)
            ->assertSee('inputmode="decimal"', false)
            ->assertSee("<span class=\"input-affix\">\u{20B9}</span>", false);

        $this->blade('<x-form.money name="amount" value="99.00" :paise="1" prefix="" />')
            ->assertSee('value="99.00"', false)
            ->assertDontSee('input-affix', false);
    }

    public function test_lookup_renders_the_combobox_and_restores_a_selection(): void
    {
        $this->withRequestSession();

        $view = $this->blade('<x-form.lookup name="student_id" label="Student" url="/app/students/lookup" :value="7" display="Aarav Sharma" sublabel="Adm. 1042" required />');

        $view->assertSee('x-data="lookup(', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('role="listbox"', false)
            ->assertSee('<input type="hidden" name="student_id"', false)
            ->assertSee('value="7"', false)
            ->assertSee('Aarav Sharma')
            ->assertSee('aria-required="true"', false);
        $this->assertMatchesRegularExpression('~app.{1,8}students.{1,8}lookup~', (string) $view);
    }

    public function test_idempotency_key_is_a_fresh_uuid_per_render(): void
    {
        $first = (string) $this->blade('<x-form.idempotency />');
        $second = (string) $this->blade('<x-form.idempotency />');

        $this->assertMatchesRegularExpression('/name="idempotency_key" value="[0-9a-f-]{36}"/', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_field_error_and_actions(): void
    {
        $this->withFailedSubmit([], ['items.0.name' => 'Name is required.']);

        $this->blade('<x-form.field label="Photo" name="photo" hint="JPG only"><input id="f-photo"></x-form.field>')
            ->assertSee('for="f-photo"', false)->assertSee('>Photo</label>', false)
            ->assertSee('id="f-photo-hint"', false);

        $this->blade('<x-form.error name="items[0][name]" />')->assertSee('id="f-items-0-name-error"', false)->assertSee('Name is required.');
        $this->blade('<x-form.error name="nothing" />')->assertDontSee('field-error', false);

        $this->blade('<x-form.actions sticky><button>Save</button></x-form.actions>')->assertSee('form-actions is-sticky', false);
    }
}
