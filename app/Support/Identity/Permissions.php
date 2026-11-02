<?php

namespace App\Support\Identity;

/**
 * The permission catalogue, assembled from config/permissions/*.php (one file per module).
 *
 * A permission is "<module>.<action>" where <module> is the config file name, e.g. "fees.payments.post".
 * Platform abilities (config/permissions/platform.php) are separate: they are not stored on roles.
 */
final class Permissions
{
    /**
     * Modules that school roles can hold permissions for.
     *
     * @return array<string, array{label: string, actions: array<string, string>, defaults: array<string, list<string>>}>
     */
    public static function modules(): array
    {
        return array_filter(
            config('permissions', []),
            fn (array $module): bool => ($module['scope'] ?? 'school') === 'school',
        );
    }

    /** @return array<string, string> permission => label, for the platform scope */
    public static function platform(): array
    {
        $out = [];
        foreach (config('permissions', []) as $key => $module) {
            if (($module['scope'] ?? 'school') === 'platform') {
                foreach ($module['actions'] as $action => $label) {
                    $out["{$key}.{$action}"] = $label;
                }
            }
        }

        return $out;
    }

    /** Every school permission name. */
    public static function allSchool(): array
    {
        $all = [];
        foreach (self::modules() as $key => $module) {
            foreach (array_keys($module['actions']) as $action) {
                $all[] = "{$key}.{$action}";
            }
        }

        return $all;
    }

    /** Every ability the Gate must know about (school + platform). */
    public static function allAbilities(): array
    {
        return [...self::allSchool(), ...array_keys(self::platform())];
    }

    /**
     * Grouped for the role editor: module key => [label, permissions => [name => label]].
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::modules() as $key => $module) {
            $groups[$key] = ['label' => $module['label'], 'permissions' => []];
            foreach ($module['actions'] as $action => $label) {
                $groups[$key]['permissions']["{$key}.{$action}"] = $label;
            }
        }

        return $groups;
    }

    public static function isSchoolPermission(string $permission): bool
    {
        return in_array($permission, self::allSchool(), true);
    }

    public static function isPlatformAbility(string $ability): bool
    {
        return array_key_exists($ability, self::platform());
    }

    /**
     * The permissions a default role starts with, expanding '*' to every action of that module.
     *
     * @return list<string>
     */
    public static function defaultsFor(string $roleSlug): array
    {
        $granted = [];
        foreach (self::modules() as $key => $module) {
            $patterns = $module['defaults'][$roleSlug] ?? [];

            foreach ($patterns as $pattern) {
                $actions = $pattern === '*' ? array_keys($module['actions']) : [$pattern];

                foreach ($actions as $action) {
                    if (array_key_exists($action, $module['actions'])) {
                        $granted[] = "{$key}.{$action}";
                    }
                }
            }
        }

        return array_values(array_unique($granted));
    }
}
