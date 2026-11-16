<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\Route;

/**
 * Role-based sidebar following the team's UI design.
 * Only links whose route exists are returned, so screens appear as they are built.
 */
class Menu
{
    /** key => [label, route name, active-route pattern] */
    private const CATALOG = [
        'home' => ['Home', 'dashboard', 'dashboard'],
        'overview' => ['Overview', 'dashboard', 'dashboard'],
        'receive' => ['Receive harvest', 'receive.create', 'receive.*'],
        'stock' => ['Stock', 'stock.index', 'stock.*'],
        'pay' => ['Pay farmers', 'payments.index', 'payments.*'],
        'receipts' => ['Receipts', 'receipts.index', 'receipts.*'],
        'farmers' => ['Farmers', 'farmers.index', 'farmers.*'],
        'employees' => ['Employees', 'employees.index', 'employees.*'],
        'audit' => ['Change history', 'audit.index', 'audit.*'],
        'rates' => ['Crop rates', 'rates.index', 'rates.*'],
    ];

    private const BY_ROLE = [
        Role::WAREHOUSE_ASSISTANT => ['home', 'receive', 'stock', 'farmers'],
        Role::FIELD_COORDINATOR => ['home', 'farmers'],
        Role::ACCOUNTING_CLERK => ['home', 'pay', 'receipts', 'farmers'],
        Role::COOPERATIVE_MANAGER => ['overview', 'employees', 'audit', 'stock', 'farmers', 'rates'],
    ];

    /**
     * @return array<int, array{label: string, route: string, url: string, active: bool}>
     */
    public static function for(string $roleCode): array
    {
        $items = [];

        foreach (self::BY_ROLE[$roleCode] ?? ['home'] as $key) {
            [$label, $route, $pattern] = self::CATALOG[$key];

            if (! Route::has($route)) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'route' => $route,
                'url' => route($route),
                'active' => request()->routeIs($pattern),
            ];
        }

        return $items;
    }

    public static function icon(string $roleCode): string
    {
        return self::svg(match ($roleCode) {
            Role::WAREHOUSE_ASSISTANT => 'warehouse',
            Role::FIELD_COORDINATOR => 'field',
            Role::ACCOUNTING_CLERK => 'payments',
            Role::COOPERATIVE_MANAGER => 'manager',
            default => 'user',
        });
    }

    public static function navIcon(string $route): string
    {
        return self::svg(match ($route) {
            'dashboard' => 'home',
            'receive.create' => 'receive',
            'stock.index' => 'stock',
            'payments.index' => 'payments',
            'receipts.index' => 'receipt',
            'farmers.index' => 'farmers',
            'employees.index' => 'employees',
            'audit.index' => 'audit',
            'rates.index' => 'rates',
            default => 'grid',
        });
    }

    private static function svg(string $name): string
    {
        $paths = match ($name) {
            'warehouse' => '<path d="M4 20V9l8-5 8 5v11M3 20h18M7 20v-6h10v6M8 10h.01M12 10h.01M16 10h.01"/>',
            'field' => '<path d="M12 20V4M12 9c-3-3-6-2-7-1 1 4 4 5 7 4M12 14c3-3 6-2 7-1-1 4-4 5-7 4"/>',
            'payments' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
            'manager' => '<path d="M4 19V9M10 19V5M16 19v-8M22 19H2"/><path d="m4 7 6-3 6 4 6-5"/>',
            'home' => '<path d="m3 10 9-7 9 7v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-9Z"/><path d="M9 20v-6h6v6"/>',
            'receive' => '<path d="M4 4h16v16H4zM8 12h8M12 8v8m0 0 3-3m-3 3-3-3"/>',
            'stock' => '<path d="m4 7 8-4 8 4-8 4-8-4ZM4 12l8 4 8-4M4 17l8 4 8-4"/>',
            'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
            'farmers' => '<circle cx="12" cy="8" r="3"/><path d="M5 21a7 7 0 0 1 14 0M4 4h4M16 4h4"/>',
            'employees' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 11a3 3 0 0 1 4 3M17 20h4"/>',
            'audit' => '<path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5"/>',
            'rates' => '<path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 5-7"/>',
            'grid' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
            default => '<circle cx="12" cy="8" r="3"/><path d="M5 21a7 7 0 0 1 14 0"/>',
        };

        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$paths.'</svg>';
    }
}
