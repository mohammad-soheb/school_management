<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

// Local-only routes (registered only when APP_ENV=local, see bootstrap/app.php).

/*
| UI gallery: every component in every variant plus sample pages. Open /_ui while `npm run dev` (or a build) is running.
| ?invalid=1 renders the forms in their validation-failed state.
*/
Route::get('/_ui', function (Request $request) {
    if ($request->boolean('invalid')) {
        // Components read the globally shared $errors (as ShareErrorsFromSession provides it), so share the
        // bag instead of View::withErrors(), which would only reach the gallery view itself.
        session()->flashInput(['email' => 'priya.nair@', 'amount' => '12x5']);
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'email' => 'Enter a valid email address.',
            'amount' => 'The amount must be a number with at most two decimals.',
            'class_id' => 'Choose a class.',
        ])));
    }

    return view('dev.ui-gallery');
})->name('dev.ui');

// Sample JSON for <x-form.lookup> in the gallery: GET /_ui/lookup?q=aar
Route::get('/_ui/lookup', function (Request $request) {
    $students = [
        ['id' => 1, 'label' => 'Aarav Sharma', 'sublabel' => 'Adm. 1042 · Class 5-A'],
        ['id' => 2, 'label' => 'Aanya Iyer', 'sublabel' => 'Adm. 1043 · Class 5-B'],
        ['id' => 3, 'label' => 'Vihaan Patel', 'sublabel' => 'Adm. 1107 · Class 7-A'],
        ['id' => 4, 'label' => 'Diya Menon', 'sublabel' => 'Adm. 1210 · Class 3-C'],
        ['id' => 5, 'label' => 'Kabir Khan', 'sublabel' => 'Adm. 1322 · Class 9-A'],
        ['id' => 6, 'label' => 'Ananya Reddy', 'sublabel' => 'Adm. 1404 · Class 6-B'],
        ['id' => 7, 'label' => 'Ishaan Gupta', 'sublabel' => 'Adm. 1521 · Class 8-A'],
    ];
    $term = mb_strtolower(trim((string) $request->query('q')));

    return response()->json(array_values(array_filter(
        $students,
        fn (array $s): bool => $term === '' || str_contains(mb_strtolower($s['label'].' '.$s['sublabel']), $term),
    )));
})->name('dev.ui.lookup');

// Slow POST used by the submit-lock demo and to see flash toasts.
Route::post('/_ui/slow', function () {
    usleep(1_500_000);

    return redirect('/_ui#buttons')->with('success', 'Saved once, however many times you clicked.');
})->name('dev.ui.slow');

// Previews of the other layouts and the error pages.
Route::view('/_ui/portal', 'dev.portal-preview')->name('dev.ui.portal');
Route::view('/_ui/print', 'dev.print-preview')->name('dev.ui.print');
Route::view('/_ui/auth/login', 'auth.login')->name('dev.ui.login');
Route::view('/_ui/auth/forgot-password', 'auth.forgot-password')->name('dev.ui.forgot');
Route::view('/_ui/auth/two-factor', 'auth.two-factor-challenge')->name('dev.ui.two-factor');
Route::view('/_ui/auth/confirm-password', 'auth.confirm-password')->name('dev.ui.confirm');
Route::get('/_ui/auth/reset-password', fn (Request $request) => view('auth.reset-password', ['request' => $request]))->name('dev.ui.reset');
Route::get('/_ui/errors/{code}', fn (int $code) => abort(in_array($code, [403, 404, 419, 429, 500, 503], true) ? $code : 404))->name('dev.ui.error');
