<?php

namespace App\View\Components\Support;

/**
 * Small helpers shared by the form.* Blade components so every control derives its
 * error key, DOM id and error message the same way.
 */
final class Field
{
    /**
     * Dotted key used for old input and validation errors: `items[0][name]` becomes `items.0.name`,
     * `roles[]` becomes `roles`.
     */
    public static function key(string $name): string
    {
        return trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    }

    /**
     * DOM id for a control: the explicit id if given, otherwise derived from the field name.
     * Two controls with the same name on one page must pass distinct ids.
     */
    public static function id(string $name, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return 'f-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    }

    /**
     * First validation message for a field (also looks at `key.*` for array fields).
     * Safe to call when the view has no `$errors` (views rendered outside a web request).
     */
    public static function error(?object $errors, string $name): ?string
    {
        if ($errors === null || $name === '') {
            return null;
        }

        $key = self::key($name);
        $message = $errors->first($key) ?: $errors->first($key.'.*');

        return $message !== '' && $message !== null ? $message : null;
    }

    /**
     * Whether the current request flashed any old input (i.e. the form was submitted and failed validation).
     */
    public static function hasOldInput(): bool
    {
        return session()->hasOldInput();
    }
}
