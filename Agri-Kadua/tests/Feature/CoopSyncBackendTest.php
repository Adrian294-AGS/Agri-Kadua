<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CropRate;
use App\Models\DeliveryReceipt;
use App\Models\FarmerMember;
use App\Models\PaymentChannel;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Database\Seeders\CoopSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoopSyncBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoopSyncSeeder::class);
    }

    public function test_seeded_accounts_have_verifiable_hashes_in_the_auth_password_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'password_hash'));

        foreach (['WA-0042', 'FC-0001', 'AC-0001', 'CM-0001'] as $employeeCode) {
            $user = User::where('employee_code', $employeeCode)->firstOrFail();
            $this->assertTrue(Hash::check('coopsync123', $user->getAuthPassword()));
        }
    }

    public function test_sign_in_requires_valid_credentials_role_and_security_acknowledgement(): void
    {
        $response = $this->post(route('login.store'), [
            'role' => Role::WAREHOUSE_ASSISTANT,
            'employee_code' => 'wa-0042',
            'password' => 'coopsync123',
            'guidelines_acknowledged' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('employee_code', 'WA-0042')->first());

        $this->assertDatabaseHas('login_events', [
            'employee_code_entered' => 'WA-0042',
            'guidelines_acknowledged' => true,
            'success' => true,
        ]);
    }

    public function test_sign_in_rejects_a_mismatched_role(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'role' => Role::ACCOUNTING_CLERK,
                'employee_code' => 'WA-0042',
                'password' => 'coopsync123',
                'guidelines_acknowledged' => '1',
            ])
            ->assertSessionHasErrors('employee_code');

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', [
            'employee_code_entered' => 'WA-0042',
            'success' => false,
        ]);
    }

    public function test_unacknowledged_guidelines_are_logged_as_a_failed_sign_in(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'role' => Role::WAREHOUSE_ASSISTANT,
                'employee_code' => 'WA-0042',
                'password' => 'coopsync123',
            ])
            ->assertSessionHasErrors('guidelines_acknowledged');

        $this->assertDatabaseHas('login_events', [
            'employee_code_entered' => 'WA-0042',
            'guidelines_acknowledged' => false,
            'success' => false,
        ]);
    }

    public function test_receipt_capture_and_storage_approval_are_atomic(): void
    {
        $user = User::where('employee_code', 'WA-0042')->firstOrFail();
        $member = FarmerMember::where('member_code', 'FM-1002')->firstOrFail();
        $variety = $member->variety;
        $schedule = $member->scheduledDeliveries()->firstOrFail();

        $this->actingAs($user)
            ->post(route('receipts.store'), [
                'member_id' => $member->member_id,
                'variety_id' => $variety->variety_id,
                'schedule_id' => $schedule->schedule_id,
                'delivery_date' => today()->toDateString(),
                'gross_weight_kg' => 1200,
                'moisture_pct' => 12,
                'impurities_pct' => 1,
                'rejected_kg' => 0,
            ])
            ->assertRedirect(route('receipts.index'));

        $receipt = DeliveryReceipt::where('member_id', $member->member_id)
            ->whereDate('delivery_date', today())
            ->firstOrFail();
        $this->assertSame('received', $schedule->fresh()->status);
        $this->assertSame('completed', $receipt->status);

        $location = StorageLocation::where('branch_id', $user->branch_id)->firstOrFail();
        $this->post(route('receipts.approve-storage', $receipt), [
            'location_id' => $location->location_id,
        ])->assertRedirect(route('receipts.index'));

        $this->assertSame('stored', $receipt->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'receipt_id' => $receipt->receipt_id,
            'movement_type' => 'receipt_in',
            'quantity_kg' => 1200,
        ]);
    }

    public function test_accounting_can_disburse_a_verified_receipt_and_issue_its_slip(): void
    {
        $user = User::where('employee_code', 'AC-0001')->firstOrFail();
        $receipt = DeliveryReceipt::where('receipt_code', 'RC-1090')->firstOrFail();
        $channel = PaymentChannel::firstOrFail();
        $variety = $receipt->variety;
        CropRate::create([
            'variety_id' => $variety->variety_id,
            'rate_per_kg' => 20,
            'effective_from' => '2025-01-01',
        ]);

        $this->actingAs($user)
            ->post(route('payments.store', $receipt), [
                'channel_id' => $channel->channel_id,
                'input_advance' => 10,
                'service_fee' => 5,
                'standard_deductions' => 0,
                'bank_reference' => 'TEST-DISBURSEMENT-1',
                'status' => 'paid',
            ])
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', [
            'receipt_id' => $receipt->receipt_id,
            'status' => 'paid',
            'final_amount' => 67985,
        ]);
        $this->assertDatabaseHas('receipt_slips', [
            'receipt_id' => $receipt->receipt_id,
        ]);
    }

    public function test_storage_approval_cannot_exceed_location_capacity(): void
    {
        $user = User::where('employee_code', 'WA-0042')->firstOrFail();
        $receipt = DeliveryReceipt::where('receipt_code', 'RC-1090')->firstOrFail();
        $location = StorageLocation::create([
            'branch_id' => $user->branch_id,
            'location_name' => 'Capacity Test Bay',
            'location_type' => 'dry_bay',
            'capacity_kg' => 1000,
        ]);

        $this->actingAs($user)
            ->from(route('receipts.index'))
            ->post(route('receipts.approve-storage', $receipt), [
                'location_id' => $location->location_id,
            ])
            ->assertSessionHasErrors('receipt');

        $this->assertSame('completed', $receipt->fresh()->status);
        $this->assertDatabaseMissing('stock_movements', [
            'receipt_id' => $receipt->receipt_id,
            'movement_type' => 'receipt_in',
        ]);
    }

    public function test_stock_release_updates_balance_and_writes_ledger_and_audit_rows(): void
    {
        $user = User::where('employee_code', 'WA-0042')->firstOrFail();
        $stock = StockItem::where('branch_id', $user->branch_id)->firstOrFail();
        $original = (float) $stock->quantity_kg;

        $this->actingAs($user)
            ->post(route('stock.release'), [
                'stock_id' => $stock->stock_id,
                'quantity_kg' => 100,
                'reason' => 'Approved warehouse release',
            ])
            ->assertRedirect(route('stock.index'));

        $this->assertSame($original - 100, (float) $stock->fresh()->quantity_kg);
        $this->assertDatabaseHas('stock_movements', [
            'stock_id' => $stock->stock_id,
            'movement_type' => 'release_out',
            'quantity_kg' => 100,
            'reason' => 'Approved warehouse release',
        ]);
        $this->assertDatabaseHas('milling_releases', [
            'status' => 'released',
            'quantity_kg' => 100,
            'created_by' => $user->user_id,
        ]);
        $this->assertSame(1, AuditLog::where('action', 'Release Stock')->count());
    }

    public function test_stock_release_rejects_an_amount_above_the_branch_balance(): void
    {
        $user = User::where('employee_code', 'WA-0042')->firstOrFail();
        $stock = StockItem::where('branch_id', $user->branch_id)->firstOrFail();
        $original = (float) $stock->quantity_kg;

        $this->actingAs($user)
            ->from(route('stock.index'))
            ->post(route('stock.release'), [
                'stock_id' => $stock->stock_id,
                'quantity_kg' => $original + 1,
                'reason' => 'Exceeds available inventory',
            ])
            ->assertSessionHasErrors('quantity_kg');

        $this->assertSame($original, (float) $stock->fresh()->quantity_kg);
        $this->assertSame(0, StockMovement::where('movement_type', 'release_out')->count());
    }

    public function test_only_warehouse_assistants_can_release_stock(): void
    {
        $manager = User::where('employee_code', 'CM-0001')->firstOrFail();
        $stock = StockItem::where('branch_id', $manager->branch_id)->firstOrFail();

        $this->actingAs($manager)
            ->post(route('stock.release'), [
                'stock_id' => $stock->stock_id,
                'quantity_kg' => 1,
                'reason' => 'Not authorized',
            ])
            ->assertForbidden();
    }

    public function test_warehouse_receiving_and_inventory_screens_render_for_warehouse_staff(): void
    {
        $user = User::where('employee_code', 'WA-0042')->firstOrFail();

        $this->actingAs($user)
            ->get(route('receive.create'))
            ->assertOk()
            ->assertSee('Record delivery');

        $this->get(route('receipts.index'))->assertOk()->assertSee('Delivery receipts');
        $this->get(route('stock.index'))->assertOk()->assertSee('Warehouse inventory');
    }

    public function test_payment_audit_and_rate_screens_render_for_authorized_roles(): void
    {
        $accountant = User::where('employee_code', 'AC-0001')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Eligible harvests');

        $manager = User::where('employee_code', 'CM-0001')->firstOrFail();
        $this->actingAs($manager)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Audit trail');

        $this->get(route('rates.index'))->assertOk()->assertSee('Current purchase rates');
        $this->get(route('stock.index'))->assertOk()->assertSee('Warehouse inventory');
    }

    public function test_farmer_detail_renders_when_expected_yield_is_missing(): void
    {
        $user = User::where('employee_code', 'WA-0042')->firstOrFail();
        $member = FarmerMember::where('member_code', 'FM-1003')->firstOrFail();

        $this->actingAs($user)
            ->get(route('farmers.show', $member))
            ->assertOk()
            ->assertSee('Expected yield')
            ->assertSee('RC-1090');
    }

    public function test_each_role_dashboard_shows_only_its_registered_navigation_links(): void
    {
        $roleNavigation = [
            Role::WAREHOUSE_ASSISTANT => [
                'WA-0042',
                ['dashboard', 'receive.create', 'stock.index', 'farmers.index'],
                ['payments.index', 'employees.index', 'audit.index'],
            ],
            Role::FIELD_COORDINATOR => [
                'FC-0001',
                ['dashboard', 'farmers.index'],
                ['receive.create', 'stock.index', 'payments.index', 'employees.index', 'audit.index'],
            ],
            Role::ACCOUNTING_CLERK => [
                'AC-0001',
                ['dashboard', 'payments.index', 'receipts.index', 'farmers.index'],
                ['receive.create', 'stock.index', 'employees.index', 'audit.index'],
            ],
            Role::COOPERATIVE_MANAGER => [
                'CM-0001',
                ['dashboard', 'employees.index', 'audit.index', 'stock.index', 'farmers.index', 'rates.index'],
                ['receive.create', 'payments.index'],
            ],
        ];

        foreach ($roleNavigation as [$employeeCode, $visibleRoutes, $hiddenRoutes]) {
            $user = User::where('employee_code', $employeeCode)->firstOrFail();
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

            foreach ($visibleRoutes as $routeName) {
                $response->assertSee(route($routeName));
            }

            foreach ($hiddenRoutes as $routeName) {
                $response->assertDontSee(route($routeName));
            }
        }
    }

    public function test_role_page_access_matrix_matches_each_roles_responsibilities(): void
    {
        $access = [
            'WA-0042' => [
                'receive.create' => 200,
                'receipts.index' => 200,
                'stock.index' => 200,
                'payments.index' => 403,
                'employees.index' => 403,
                'audit.index' => 403,
                'rates.index' => 403,
            ],
            'FC-0001' => [
                'receive.create' => 403,
                'receipts.index' => 403,
                'stock.index' => 403,
                'payments.index' => 403,
                'employees.index' => 403,
                'audit.index' => 403,
                'rates.index' => 403,
            ],
            'AC-0001' => [
                'receive.create' => 403,
                'receipts.index' => 200,
                'stock.index' => 403,
                'payments.index' => 200,
                'employees.index' => 403,
                'audit.index' => 403,
                'rates.index' => 403,
            ],
            'CM-0001' => [
                'receive.create' => 403,
                'receipts.index' => 200,
                'stock.index' => 200,
                'payments.index' => 200,
                'employees.index' => 200,
                'audit.index' => 200,
                'rates.index' => 200,
            ],
        ];

        foreach ($access as $employeeCode => $routes) {
            $this->actingAs(User::where('employee_code', $employeeCode)->firstOrFail());

            foreach ($routes as $routeName => $expectedStatus) {
                $this->get(route($routeName))->assertStatus($expectedStatus);
            }
        }
    }

    public function test_role_action_access_matrix_blocks_unauthorized_writes(): void
    {
        $receipt = DeliveryReceipt::where('receipt_code', 'RC-1090')->firstOrFail();
        $actions = [
            'record_receipt' => fn () => $this->post(route('receipts.store')),
            'approve_storage' => fn () => $this->post(route('receipts.approve-storage', $receipt)),
            'release_stock' => fn () => $this->post(route('stock.release')),
            'record_payment' => fn () => $this->post(route('payments.store', $receipt)),
            'create_employee' => fn () => $this->post(route('employees.store')),
        ];
        $expectedStatuses = [
            'WA-0042' => [
                'record_receipt' => 302,
                'approve_storage' => 302,
                'release_stock' => 302,
                'record_payment' => 403,
                'create_employee' => 403,
            ],
            'FC-0001' => [
                'record_receipt' => 403,
                'approve_storage' => 403,
                'release_stock' => 403,
                'record_payment' => 403,
                'create_employee' => 403,
            ],
            'AC-0001' => [
                'record_receipt' => 403,
                'approve_storage' => 403,
                'release_stock' => 403,
                'record_payment' => 302,
                'create_employee' => 403,
            ],
            'CM-0001' => [
                'record_receipt' => 403,
                'approve_storage' => 403,
                'release_stock' => 403,
                'record_payment' => 403,
                'create_employee' => 302,
            ],
        ];

        foreach ($expectedStatuses as $employeeCode => $roleStatuses) {
            $this->actingAs(User::where('employee_code', $employeeCode)->firstOrFail());

            foreach ($actions as $actionName => $action) {
                $this->assertSame(
                    $roleStatuses[$actionName],
                    $action()->getStatusCode(),
                    $employeeCode.' action '.$actionName,
                );
            }
        }
    }

    public function test_cooperative_manager_can_create_an_employee_account_for_their_branch(): void
    {
        $manager = User::where('employee_code', 'CM-0001')->firstOrFail();
        $role = Role::where('role_code', Role::FIELD_COORDINATOR)->firstOrFail();

        $this->actingAs($manager)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Create employee account')
            ->assertSee('Field Coordinator');

        $this->post(route('employees.store'), [
            'employee_code' => 'fc-0100',
            'full_name' => 'New Field Employee',
            'job_title' => 'Field Coordinator',
            'email' => 'new.employee@example.com',
            'phone' => '09123456789',
            'role_id' => $role->role_id,
            'password' => 'Strong-Initial-Pass-2026',
            'password_confirmation' => 'Strong-Initial-Pass-2026',
        ])->assertRedirect(route('employees.index'));

        $employee = User::where('employee_code', 'FC-0100')->firstOrFail();
        $this->assertSame($manager->branch_id, $employee->branch_id);
        $this->assertSame($role->role_id, $employee->role_id);
        $this->assertTrue(Hash::check('Strong-Initial-Pass-2026', $employee->getAuthPassword()));
        $this->assertDatabaseHas('audit_logs', [
            'record_ref' => 'FC-0100',
            'action' => 'Create Employee Account',
            'user_id' => $manager->user_id,
        ]);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'role' => Role::FIELD_COORDINATOR,
            'employee_code' => 'fc-0100',
            'password' => 'Strong-Initial-Pass-2026',
            'guidelines_acknowledged' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($employee);
    }

    public function test_only_cooperative_managers_can_create_employee_accounts(): void
    {
        $accountant = User::where('employee_code', 'AC-0001')->firstOrFail();
        $role = Role::where('role_code', Role::WAREHOUSE_ASSISTANT)->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('employees.index'))
            ->assertForbidden();

        $this->post(route('employees.store'), [
            'employee_code' => 'WA-0100',
            'full_name' => 'Unauthorized Employee',
            'role_id' => $role->role_id,
            'password' => 'Strong-Initial-Pass-2026',
            'password_confirmation' => 'Strong-Initial-Pass-2026',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['employee_code' => 'WA-0100']);
    }

    public function test_employee_account_cannot_be_created_with_a_duplicate_id_or_manager_role(): void
    {
        $manager = User::where('employee_code', 'CM-0001')->firstOrFail();
        $managerRole = Role::where('role_code', Role::COOPERATIVE_MANAGER)->firstOrFail();

        $this->actingAs($manager)
            ->from(route('employees.index'))
            ->post(route('employees.store'), [
                'employee_code' => 'WA-0042',
                'full_name' => 'Duplicate Employee',
                'role_id' => Role::where('role_code', Role::WAREHOUSE_ASSISTANT)->value('role_id'),
                'password' => 'Strong-Initial-Pass-2026',
                'password_confirmation' => 'Strong-Initial-Pass-2026',
            ])
            ->assertSessionHasErrors('employee_code');

        $this->post(route('employees.store'), [
            'employee_code' => 'CM-0100',
            'full_name' => 'New Manager',
            'role_id' => $managerRole->role_id,
            'password' => 'Strong-Initial-Pass-2026',
            'password_confirmation' => 'Strong-Initial-Pass-2026',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['employee_code' => 'CM-0100']);
    }
}
