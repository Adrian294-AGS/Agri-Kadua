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
        'pay' => ['Pay farmers', 'pay.index', 'pay.*'],
        'receipts' => ['Receipts', 'receipts.index', 'receipts.*'],
        'farmers' => ['Farmers', 'farmers.index', 'farmers.*'],
        'audit' => ['Change history', 'audit.index', 'audit.*'],
        'rates' => ['Crop rates', 'rates.index', 'rates.*'],
    ];

    private const BY_ROLE = [
        Role::WAREHOUSE_ASSISTANT => ['home', 'receive', 'stock', 'farmers'],
        Role::FIELD_COORDINATOR => ['home', 'farmers'],
        Role::ACCOUNTING_CLERK => ['home', 'pay', 'receipts', 'farmers'],
        Role::COOPERATIVE_MANAGER => ['overview', 'audit', 'stock', 'farmers', 'rates'],
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
        return match ($roleCode) {
            Role::WAREHOUSE_ASSISTANT => '🏭',
            Role::FIELD_COORDINATOR => '🌾',
            Role::ACCOUNTING_CLERK => '💰',
            Role::COOPERATIVE_MANAGER => '📊',
            default => '👤',
        };
    }
}