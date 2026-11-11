<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Crop;
use App\Models\CropRate;
use App\Models\CropVariety;
use App\Models\DeliveryReceipt;
use App\Models\FarmerMember;
use App\Models\FieldInputRequest;
use App\Models\InputCategory;
use App\Models\InputProduct;
use App\Models\InputStock;
use App\Models\MillingRelease;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\ReceiptSlip;
use App\Models\ReportTemplate;
use App\Models\Role;
use App\Models\ScheduledDelivery;
use App\Models\StockItem;
use App\Models\StorageLocation;
use App\Models\SyncModuleStatus;
use App\Models\SyncRecord;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data taken from the Co-opSync wireframes.
 * All demo accounts use the password "coopsync123".
 */
class CoopSyncSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Organisation and access ---------------------------------------
        $branch = Branch::create([
            'branch_code' => 'SM-01',
            'branch_name' => 'San Miguel Farmers Cooperative',
            'region' => null,
            'currency_code' => 'PHP',
        ]);

        $terminal = Terminal::create([
            'branch_id' => $branch->branch_id,
            'device_code' => 'W-402',
            'description' => 'Warehouse Terminal',
        ]);

        $roles = [];
        foreach ([
            Role::WAREHOUSE_ASSISTANT => ['Warehouse Assistant', 'Harvest receiving, quality recording, inventory'],
            Role::FIELD_COORDINATOR => ['Field Coordinator', 'Expected deliveries, schedule changes, input requests'],
            Role::ACCOUNTING_CLERK => ['Accounting Clerk', 'Payments, reconciliation, receipts'],
            Role::COOPERATIVE_MANAGER => ['Cooperative Manager', 'Dashboard, reports, audit trail, member registry'],
        ] as $code => [$name, $description]) {
            $roles[$code] = Role::create([
                'role_code' => $code,
                'role_name' => $name,
                'description' => $description,
            ]);
        }

        $makeUser = fn (string $code, string $name, string $title, string $roleCode) => User::create([
            'employee_code' => $code,
            'full_name' => $name,
            'job_title' => $title,
            'password_hash' => 'coopsync123',
            'role_id' => $roles[$roleCode]->role_id,
            'branch_id' => $branch->branch_id,
        ]);

        $jose = $makeUser('WA-0042', 'Jose Mendoza', 'Warehouse Assistant', Role::WAREHOUSE_ASSISTANT);
        $ana = $makeUser('FC-0001', 'Ana Reyes', 'Field Coordinator', Role::FIELD_COORDINATOR);
        $clara = $makeUser('AC-0001', 'Clara Ocampo', 'Accounting Clerk', Role::ACCOUNTING_CLERK);
        $mira = $makeUser('CM-0001', 'Mira Cole', 'Cooperative Manager', Role::COOPERATIVE_MANAGER);

        // ---- Reference data --------------------------------------------------
        $crops = [];
        foreach (['Coffee', 'Paddy Rice', 'Corn', 'Sweet Potato'] as $name) {
            $crops[$name] = Crop::create(['crop_name' => $name]);
        }

        $varieties = [];
        foreach ([
            'arabica' => ['Coffee', 'Arabica Red Catimor'],
            'robusta' => ['Coffee', 'Robusta'],
            'paddy' => ['Paddy Rice', 'High-Yield Paddy RC-228'],
            'yellow_corn' => ['Corn', 'Yellow Corn Sweet-Corn F1'],
            'maize' => ['Corn', 'Organic Maize'],
            'sweet_potato' => ['Sweet Potato', 'Sweet Potato'],
        ] as $key => [$cropName, $varietyName]) {
            $varieties[$key] = CropVariety::create([
                'crop_id' => $crops[$cropName]->crop_id,
                'variety_name' => $varietyName,
            ]);
        }

        // Base purchase rates (Arabica from the receipt; maize derived from 93,000 / 3,100 kg).
        CropRate::create(['variety_id' => $varieties['arabica']->variety_id, 'rate_per_kg' => 120.00, 'effective_from' => '2025-01-01']);
        CropRate::create(['variety_id' => $varieties['maize']->variety_id, 'rate_per_kg' => 30.00, 'effective_from' => '2025-01-01']);

        $locations = [];
        foreach ([
            'silo_4b' => ['Silo Section 4B', 'silo'],
            'bay_1' => ['Warehouse Dry Bay 1', 'dry_bay'],
            'bay_3' => ['Warehouse Dry Bay 3', 'dry_bay'],
            'bay_2' => ['Warehouse Dry Bay 2', 'dry_bay'],
        ] as $key => [$name, $type]) {
            $locations[$key] = StorageLocation::create([
                'branch_id' => $branch->branch_id,
                'location_name' => $name,
                'location_type' => $type,
            ]);
        }

        $seeds = InputCategory::create(['category_name' => 'Seeds']);
        $fertilizer = InputCategory::create(['category_name' => 'Organic Fertilizer']);
        InputCategory::create(['category_name' => 'Tools']);

        $fertilizerProduct = InputProduct::create([
            'category_id' => $fertilizer->category_id,
            'product_name' => 'Organic Fertilizer',
            'unit_label' => 'bag (50kg)',
            'unit_price' => 1875.00,
            'subsidy_rate_pct' => 80.00,
        ]);

        InputStock::create([
            'product_id' => $fertilizerProduct->product_id,
            'location_id' => $locations['bay_1']->location_id,
            'qty_on_hand' => 200,
            'qty_reserved' => 0,
        ]);

        $channel = PaymentChannel::create(['channel_name' => 'Cooperative Rural Bank - Account Transfer']);

        foreach ([
            'Harvest Summary Report',
            'Inventory Summary Report',
            'Member Summary Report',
            'Payment Summary Report',
            'End-Of-Month Reconciliation PDF',
        ] as $templateName) {
            ReportTemplate::create(['template_name' => $templateName]);
        }

        foreach ([
            ['Farmer Records', 'online'],
            ['Warehouse Scales', 'online'],
            ['Silo Inventory', 'online'],
            ['Milling Outbound', 'degraded'],
        ] as [$moduleName, $status]) {
            SyncModuleStatus::create(['module_name' => $moduleName, 'status' => $status]);
        }

        // ---- Farmer-members --------------------------------------------------
        $members = [];
        foreach ([
            ['FM-1002', 'Ramon Valenzuela', '+63 917 123 4567', 'Barangay San Jose, Lot 4A & 4B', 14.5, 'Clay Loam (Highly Fertile)', 'Coffee', 'arabica', 3500, 'Q2 harvest', 'active'],
            ['FM-1003', 'Elena Santos', null, null, 8.2, null, 'Paddy Rice', 'paddy', null, null, 'active'],
            ['FM-1004', 'Josephine Cruz', null, null, 12.0, null, 'Corn', 'yellow_corn', null, null, 'inactive'],
            ['FM-1005', 'Mateo Alcantara', null, null, 22.1, null, 'Sweet Potato', 'sweet_potato', null, null, 'active'],
            ['FM-1006', 'Simeon De Vega', null, null, 5.0, null, 'Coffee', 'robusta', null, null, 'active'],
        ] as [$code, $name, $phone, $land, $size, $soil, $cropName, $varietyKey, $yield, $season, $status]) {
            $members[$code] = FarmerMember::create([
                'member_code' => $code,
                'full_name' => $name,
                'phone' => $phone,
                'land_location' => $land,
                'farm_size_ha' => $size,
                'soil_classification' => $soil,
                'main_crop_id' => $crops[$cropName]->crop_id,
                'primary_variety_id' => $varieties[$varietyKey]->variety_id,
                'expected_yield_kg' => $yield,
                'expected_yield_season' => $season,
                'status' => $status,
                'registered_on' => '2021-01-01',
                'branch_id' => $branch->branch_id,
                'coordinator_id' => $ana->user_id,
                'created_by' => $mira->user_id,
            ]);
        }

        // ---- Field portal: expected deliveries -------------------------------
        $schedule1 = ScheduledDelivery::create([
            'member_id' => $members['FM-1002']->member_id,
            'variety_id' => $varieties['arabica']->variety_id,
            'scheduled_date' => '2025-10-24',
            'estimated_kg' => 1250,
            'status' => 'on_time',
            'created_by' => $ana->user_id,
        ]);
        $schedule2 = ScheduledDelivery::create([
            'member_id' => $members['FM-1003']->member_id,
            'variety_id' => $varieties['paddy']->variety_id,
            'scheduled_date' => '2025-10-25',
            'estimated_kg' => 4000,
            'status' => 'silo_pending',
            'created_by' => $ana->user_id,
        ]);
        ScheduledDelivery::create([
            'member_id' => $members['FM-1005']->member_id,
            'variety_id' => $varieties['sweet_potato']->variety_id,
            'scheduled_date' => '2025-10-26',
            'estimated_kg' => 1800,
            'status' => 'scheduled',
            'created_by' => $ana->user_id,
        ]);

        // ---- Delivery receipts -----------------------------------------------
        $receipt = function (string $code, ?int $scheduleId, string $memberCode, string $varietyKey, string $date, float $gross, float $moisture, float $impurities, float $deductionPct, float $rejected, string $status) use ($members, $varieties, $branch, $terminal, $jose) {
            $deductionKg = round($gross * $deductionPct / 100, 2);

            return DeliveryReceipt::create([
                'receipt_code' => $code,
                'schedule_id' => $scheduleId,
                'member_id' => $members[$memberCode]->member_id,
                'variety_id' => $varieties[$varietyKey]->variety_id,
                'branch_id' => $branch->branch_id,
                'terminal_id' => $terminal->terminal_id,
                'delivery_date' => $date,
                'gross_weight_kg' => $gross,
                'moisture_pct' => $moisture,
                'impurities_pct' => $impurities,
                'deduction_pct' => $deductionPct,
                'deduction_kg' => $deductionKg,
                'rejected_kg' => $rejected,
                'net_weight_kg' => round($gross - $deductionKg - $rejected, 2),
                'status' => $status,
                'verification_status' => 'verified',
                'received_by' => $jose->user_id,
                'completed_at' => $date.' 10:00:00',
            ]);
        };

        $rc1070 = $receipt('RC-1070', null, 'FM-1002', 'arabica', '2025-08-15', 850, 12.0, 1.0, 0, 0, 'stored');
        $rc1080 = $receipt('RC-1080', null, 'FM-1002', 'maize', '2025-09-28', 3100, 12.0, 1.0, 0, 0, 'stored');
        $rc1086 = $receipt('RC-1086', null, 'FM-1002', 'arabica', '2025-10-12', 1240, 12.0, 1.0, 0, 0, 'stored');
        $receipt('RC-1090', $schedule2->schedule_id, 'FM-1003', 'paddy', '2025-10-24', 3400, 12.0, 1.2, 0, 0, 'completed');
        // Warehouse Receiving screen: 1,250 - 25 (2.0%) - 15 rejected = 1,210.0 kg
        $rc1091 = $receipt('RC-1091', $schedule1->schedule_id, 'FM-1002', 'arabica', '2025-10-24', 1250, 13.5, 1.2, 2.0, 15, 'completed');

        // ---- Payments --------------------------------------------------------
        $pay = function (string $code, DeliveryReceipt $rc, string $memberCode, string $varietyKey, float $rate, float $advance, float $fee, string $status, ?string $paidAt, ?string $bankRef) use ($members, $varieties, $channel, $clara) {
            $gross = round((float) $rc->net_weight_kg * $rate, 2);

            return Payment::create([
                'payment_code' => $code,
                'member_id' => $members[$memberCode]->member_id,
                'receipt_id' => $rc->receipt_id,
                'variety_id' => $varieties[$varietyKey]->variety_id,
                'net_weight_kg' => $rc->net_weight_kg,
                'base_rate_per_kg' => $rate,
                'gross_value' => $gross,
                'input_advance' => $advance,
                'service_fee' => $fee,
                'standard_deductions' => 0,
                'final_amount' => round($gross - $advance - $fee, 2),
                'channel_id' => $channel->channel_id,
                'bank_reference' => $bankRef,
                'status' => $status,
                'paid_at' => $paidAt,
                'recorded_by' => $clara->user_id,
            ]);
        };

        $pay('REC-881070', $rc1070, 'FM-1002', 'arabica', 120.00, 0, 0, 'pending', null, null);
        $pay('REC-881080', $rc1080, 'FM-1002', 'maize', 30.00, 0, 0, 'paid', '2025-09-30 09:00:00', null);
        $pay('REC-881086', $rc1086, 'FM-1002', 'arabica', 120.00, 0, 0, 'paid', '2025-10-14 09:00:00', null);
        // 1,210 kg x 120 = 145,200.00 - 7,500.00 advance - 2,078.00 service fee = 135,622.00
        $payment1091 = $pay('REC-882109', $rc1091, 'FM-1002', 'arabica', 120.00, 7500.00, 2078.00, 'paid', '2025-10-24 14:15:22', 'TXN-BKR-99382109');

        ReceiptSlip::create([
            'slip_code' => 'REC-2025-1024-88A',
            'receipt_id' => $rc1091->receipt_id,
            'payment_id' => $payment1091->payment_id,
            'barcode_value' => '*REC2025102488A*',
            'issued_by' => $clara->user_id,
            'issued_at' => '2025-10-24 14:15:00',
        ]);

        // ---- Inventory ---------------------------------------------------------
        foreach ([
            ['arabica', 'silo_4b', 14200, 5000],
            ['paddy', 'bay_1', 4500, 5000],
            ['yellow_corn', 'bay_3', 22800, 5000],
            ['sweet_potato', 'bay_2', 850, 1000],
        ] as [$varietyKey, $locationKey, $quantity, $threshold]) {
            StockItem::create([
                'branch_id' => $branch->branch_id,
                'variety_id' => $varieties[$varietyKey]->variety_id,
                'location_id' => $locations[$locationKey]->location_id,
                'quantity_kg' => $quantity,
                'low_stock_threshold_kg' => $threshold,
            ]);
        }

        MillingRelease::create([
            'milling_order_code' => 'MO-8832',
            'variety_id' => $varieties['paddy']->variety_id,
            'quantity_kg' => 3400,
            'destination' => 'Regional distributors',
            'status' => 'authorised',
            'authorised_by' => $mira->user_id,
            'authorised_at' => '2025-10-24 11:00:00',
            'created_by' => $jose->user_id,
        ]);

        // 15 bags x 1,875.00 = 28,125.00 ; 80% subsidy = 22,500.00 ; member share = 5,625.00
        FieldInputRequest::create([
            'request_code' => 'FIR-0001',
            'member_id' => $members['FM-1002']->member_id,
            'product_id' => $fertilizerProduct->product_id,
            'quantity' => 15,
            'unit_price' => 1875.00,
            'subsidy_rate_pct' => 80.00,
            'total_value' => 28125.00,
            'subsidy_value' => 22500.00,
            'member_share' => 5625.00,
            'pickup_date' => '2025-11-01',
            'status' => 'draft',
            'submitted_by' => $ana->user_id,
        ]);

        // ---- Sync records ----------------------------------------------------
        foreach ([
            ['TX-9921', 'warehouse_receiving', 1.2, 'saved', 0, null, 12, true],
            ['TX-9922', 'warehouse_receiving', 2.4, 'syncing', 0, null, 0, false],
            ['TX-9923', 'field_coordinator', 0.8, 'saved', 0, null, 60, true],
            ['TX-9924', 'field_coordinator', 1.5, 'retry', 1, 'Network timeout', 120, false],
        ] as [$code, $module, $size, $status, $retries, $error, $secondsAgo, $synced]) {
            SyncRecord::create([
                'record_code' => $code,
                'source_module' => $module,
                'size_kb' => $size,
                'status' => $status,
                'retry_count' => $retries,
                'last_error' => $error,
                'terminal_id' => $terminal->terminal_id,
                'created_at' => now()->subSeconds($secondsAgo),
                'synced_at' => $synced ? now() : null,
            ]);
        }

        // ---- Audit trail -----------------------------------------------------
        AuditLog::record('farmer_members', 'FM-1002', 'Update Profile', 'Soil classification corrected', 'Clay', 'Clay Loam (Highly Fertile)', $mira->user_id, null, $terminal->terminal_id, '2025-10-24 14:32:10');
        AuditLog::record('payments', 'REC-882109', 'Disburse Pay', 'Payment approved', '₱0.00', '₱135,622.00', $clara->user_id, null, $terminal->terminal_id, '2025-10-24 14:15:22');
        AuditLog::record('warehouse_receiving', 'RC-1090', 'Value Override', 'Impurities adjusted after re-sieve', '1.5%', '1.2%', $jose->user_id, null, $terminal->terminal_id, '2025-10-24 13:48:01');
        AuditLog::record('sync', 'TX-9924', 'Sync Failure', 'Network timeout', 'Pending', 'Retrying', null, 'System Scaler', $terminal->terminal_id, '2025-10-24 12:04:15');
    }
}
