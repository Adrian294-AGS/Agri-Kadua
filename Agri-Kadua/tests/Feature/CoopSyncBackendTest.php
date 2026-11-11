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
use Tests\TestCase;

class CoopSyncBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoopSyncSeeder::class);
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
}
