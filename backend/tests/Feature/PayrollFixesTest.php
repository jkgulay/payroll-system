<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\User;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Suite for Payroll System Fixes
 * 
 * Run with: php artisan test --filter PayrollFixesTest
 */
class PayrollFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Hourly rate calculation for daily workers
     */
    public function test_daily_worker_hourly_rate_calculation()
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'daily',
            'basic_salary' => 550.00 // Daily rate
        ]);

        $hourlyRate = $employee->getHourlyRate();

        // Expected: 550 / 8 = 68.75
        $this->assertEquals(68.75, $hourlyRate);
    }

    /**
     * Test: Hourly rate calculation for monthly workers
     */
    public function test_monthly_worker_hourly_rate_calculation()
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'monthly',
            'basic_salary' => 22000.00 // Monthly salary
        ]);

        $hourlyRate = $employee->getHourlyRate();

        // Expected: 22000 / (22 days * 8 hours) = 125.00
        $this->assertEquals(125.00, $hourlyRate);
    }

    /**
     * Test: Payroll authorization - only admin can approve
     */
    public function test_payroll_approval_requires_admin_role()
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $payroll = Payroll::factory()->create(['status' => 'recommended']);

        $response = $this->actingAs($employee)
            ->postJson("/api/payroll/{$payroll->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test: Payroll authorization - admin can approve
     */
    public function test_admin_can_approve_payroll()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payroll = Payroll::factory()->create(['status' => 'recommended']);

        $response = $this->actingAs($admin)
            ->postJson("/api/payroll/{$payroll->id}/approve");

        $response->assertStatus(200);
        $this->assertEquals('approved', $payroll->fresh()->status);
    }

    /**
     * Test: Cannot create overlapping payroll periods
     */
    public function test_cannot_create_overlapping_payroll_periods()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create first payroll
        Payroll::factory()->create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
        ]);

        // Try to create overlapping payroll
        $response = $this->actingAs($admin)
            ->postJson('/api/payroll', [
                'period_start_date' => '2026-01-10',
                'period_end_date' => '2026-01-20',
                'payment_date' => '2026-01-25',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'A payroll period already exists for this date range. Please check existing payroll periods.'
        ]);
    }

    /**
     * Test: Loan balance updates after payroll marked as paid
     */
    public function test_loan_balance_updates_after_payroll_paid()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = Employee::factory()->create();

        $loan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'principal_amount' => 10000,
            'balance' => 10000,
            'monthly_amortization' => 1000,
            'status' => 'active'
        ]);

        $payroll = Payroll::factory()->create(['status' => 'approved']);
        $payrollItem = PayrollItem::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
        ]);

        $payrollService = app(PayrollService::class);
        $payrollService->markAsPaid($payroll, $admin->id);

        // Balance should decrease by monthly_amortization / 2 (semi-monthly)
        $loan->refresh();
        $this->assertEquals(9500, $loan->balance);
        $this->assertEquals(500, $loan->amount_paid);
    }

    /**
     * Test: Accountant can add allowance with audit trail
     */
    public function test_accountant_can_add_allowance_with_audit_trail()
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $employee = Employee::factory()->create();
        $payroll = Payroll::factory()->create(['status' => 'draft']);

        PayrollItem::factory()->create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
        ]);

        $response = $this->actingAs($accountant)
            ->postJson('/api/accountant/payslip-modification', [
                'employee_id' => $employee->id,
                'additional_allowance' => 500.00,
                'notes' => 'Performance bonus'
            ]);

        $response->assertStatus(200);

        // Check allowance was created
        $this->assertDatabaseHas('employee_allowances', [
            'employee_id' => $employee->id,
            'amount' => 500.00,
            'created_by' => $accountant->id,
        ]);
    }

    /**
     * Test: Government contributions based on actual earnings for daily workers
     */
    public function test_government_contributions_for_daily_worker_with_partial_attendance()
    {
        $employee = Employee::factory()->create([
            'salary_type' => 'daily',
            'basic_salary' => 550.00
        ]);

        $payroll = Payroll::factory()->create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
        ]);

        // Worker only worked 5 days out of 11
        for ($i = 0; $i < 5; $i++) {
            Attendance::factory()->create([
                'employee_id' => $employee->id,
                'attendance_date' => "2026-01-0" . ($i + 1),
                'status' => 'present',
                'regular_hours' => 8,
            ]);
        }

        $payrollService = app(PayrollService::class);
        $payrollItem = $payrollService->processEmployeePayroll($payroll, $employee);

        // Gross pay should be 5 days * 550 = 2750
        $this->assertEquals(2750, $payrollItem->gross_pay);

        // Government contributions should be based on estimated monthly (2750 * 2 = 5500)
        // Not on full monthly rate (550 * 22 = 12100)
        $this->assertLessThan(300, $payrollItem->sss_contribution); // Should be lower
    }

    /**
     * Test: Allowance calculation uses working days not calendar days
     */
    public function test_daily_allowance_uses_working_days()
    {
        $employee = Employee::factory()->create();

        $allowance = EmployeeAllowance::factory()->create([
            'employee_id' => $employee->id,
            'allowance_type' => 'meal',
            'amount' => 100.00,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $payroll = Payroll::factory()->create([
            'period_start' => '2026-01-01', // Thursday
            'period_end' => '2026-01-15',   // Thursday
        ]);

        $payrollService = app(PayrollService::class);

        // Period has 2 Sundays (Jan 5 and Jan 12)
        // So 15 days - 2 Sundays = 13 working days
        // Expected: 100 * 13 = 1300

        $allowances = $this->invokeMethod(
            $payrollService,
            'calculateAllowances',
            [$employee, $payroll]
        );

        // The allowance should not be 1500 (15 calendar days)
        $this->assertNotEquals(1500, $allowances['other_allowances']);
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
