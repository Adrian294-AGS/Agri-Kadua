<?php

namespace Tests\Unit;

use App\Services\PaymentService;
use App\Services\ReceivingService;
use App\Services\TransmissionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Checks the calculations against the numbers shown in the wireframes.
 */
class CoopSyncCalculationTest extends TestCase
{
    private function receiving(): ReceivingService
    {
        return new ReceivingService(new TransmissionService());
    }

    private function payments(): PaymentService
    {
        return new PaymentService(new TransmissionService());
    }

    public function test_deduction_matches_the_wireframe_example(): void
    {
        $this->assertSame(2.0, $this->receiving()->suggestDeductionPct(13.5, 1.2, 12.0, 1.0));
    }

    public function test_no_deduction_when_within_limits(): void
    {
        $this->assertSame(0.0, $this->receiving()->suggestDeductionPct(12.0, 1.0, 12.0, 1.0));
        $this->assertSame(0.0, $this->receiving()->suggestDeductionPct(11.0, 0.5, 12.0, 1.0));
    }

    public function test_net_weight_matches_the_wireframe_example(): void
    {
        $result = $this->receiving()->calculateNet(1250, 2.0, 15);

        $this->assertSame(25.0, $result['deduction_kg']);
        $this->assertSame(1210.0, $result['net_weight_kg']);
    }

    public function test_net_weight_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->receiving()->calculateNet(100, 2.0, 500);
    }

    public function test_gross_weight_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->receiving()->calculateNet(0, 0, 0);
    }

    public function test_payment_matches_the_receipt_wireframe(): void
    {
        $result = $this->payments()->calculate(1210, 120, 7500, 2078);

        $this->assertSame(145200.0, $result['gross_value']);
        $this->assertSame(135622.0, $result['final_amount']);
    }

    public function test_deductions_cannot_exceed_the_harvest_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->payments()->calculate(10, 10, 500, 0);
    }
}