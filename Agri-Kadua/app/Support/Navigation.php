<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\Route;

/**
 * Role-based sidebar. Only links whose route already exists are returned.
 */
class Navigation
{
    /**
     * @return array<int, array{label: string, route: string, url: string}>
     */
    public static function for(string $roleCode): array
    {
        $catalog = [
            'dashboard' => ['Dashboard', 'dashboard'],
            'dashboard_reports' => ['Dashboard & Reports', 'dashboard'],
            'reports' => ['Reports & Export', 'reports.index'],
            'members' => ['Farmer Members', 'members.index'],
            'receiving' => ['Warehouse Receiving', 'receiving.index'],
            'inventory' => ['Inventory & Stocks', 'inventory.index'],
            'field' => ['Field Portal', 'field.index'],
            'sync' => ['Sync & Transmission', 'sync.index'],
            'payments' => ['Payment & Recon', 'payments.index'],
            'receipts' => ['Receipt', 'receipts.index'],
            'audit' => ['Audit Trail Logs', 'audit.index'],
        ];

        $byRole = [
            Role::WAREHOUSE_ASSISTANT => ['dashboard', 'members', 'receiving', 'inventory', 'sync', 'receipts'],
            Role::FIELD_COORDINATOR => ['dashboard', 'members', 'field', 'sync'],
            Role::ACCOUNTING_CLERK => ['dashboard', 'members', 'payments', 'receipts'],
            Role::COOPERATIVE_MANAGER => ['dashboard_reports', 'reports', 'members', 'inventory', 'audit'],
        ];

        $items = [];

        foreach ($byRole[$roleCode] ?? ['dashboard'] as $key) {
            [$label, $route] = $catalog[$key];

            if (Route::has($route)) {
                $items[] = ['label' => $label, 'route' => $route, 'url' => route($route)];
            }
        }

        return $items;
    }
}