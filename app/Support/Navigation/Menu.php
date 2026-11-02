<?php

namespace App\Support\Navigation;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar from per-module fragments, so modules add a file instead of editing a shared menu.
 *
 * Fragments live in resources/menus/{school|platform|portal}/*.php and return:
 *   ['group' => 'Students', 'order' => 20, 'items' => [
 *       ['label' => 'Students', 'route' => 'school.students.index', 'icon' => 'academic-cap',
 *        'can' => 'students.view', 'active' => 'school.students.*', 'order' => 10],
 *   ]]
 *
 * Items whose route does not exist yet, or whose permission the user lacks, are dropped.
 * Fragments with the same group label are merged.
 */
final class Menu
{
    /**
     * @return list<array{label: string, items: list<array{label: string, url: string, icon: string, active: bool, badge: ?string}>}>
     */
    public function build(string $area): array
    {
        $groups = [];

        $files = glob(resource_path("menus/{$area}/*.php")) ?: [];
        sort($files);

        foreach ($files as $file) {
            $loaded = require $file;

            // A file returns one fragment ['group' => ...] or a list of fragments.
            foreach (isset($loaded['group']) ? [$loaded] : $loaded as $fragment) {
                $label = $fragment['group'];
                $groups[$label]['order'] = min($groups[$label]['order'] ?? PHP_INT_MAX, $fragment['order'] ?? 100);
                $groups[$label]['items'] ??= [];

                foreach ($fragment['items'] as $item) {
                    if (! Route::has($item['route'])) {
                        continue;
                    }

                    $can = $item['can'] ?? null;
                    if ($can !== null && ! Gate::allows($can)) {
                        continue;
                    }

                    $groups[$label]['items'][] = [
                        'order' => $item['order'] ?? 100,
                        'label' => $item['label'],
                        'url' => route($item['route'], $item['params'] ?? []),
                        'icon' => $item['icon'] ?? 'squares-2x2',
                        'active' => request()->routeIs($item['active'] ?? $item['route']),
                        'badge' => isset($item['badge']) && is_callable($item['badge']) ? ($item['badge'])() : ($item['badge'] ?? null),
                    ];
                }
            }
        }

        uasort($groups, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $menu = [];
        foreach ($groups as $label => $group) {
            if (empty($group['items'])) {
                continue;
            }

            usort($group['items'], fn (array $a, array $b): int => $a['order'] <=> $b['order']);

            $menu[] = [
                'label' => $label,
                'items' => array_map(fn (array $i): array => array_diff_key($i, ['order' => 0]), $group['items']),
            ];
        }

        return $menu;
    }
}
