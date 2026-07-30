<?php

namespace Tests\Feature;

use App\Models\LeaveBalanceMovement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_and_user_roles_have_expected_access(): void
    {
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail();
        $admin = User::where('email', 'javierperezlopez1204@gmail.com')->firstOrFail();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('admin.rules.edit'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.rules.edit'))
            ->assertOk();
    }

    public function test_employee_can_request_vacation_and_admin_can_approve_it(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail();
        $admin = User::where('email', 'javierperezlopez1204@gmail.com')->firstOrFail();
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $vacations->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'user_comment' => 'Vacaciones familiares.',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('employee_profile_id', $employee->employeeProfile->id)->firstOrFail();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $leaveRequest->status);
        $this->assertSame(5, $leaveRequest->requested_units);
        $this->assertDatabaseHas('request_events', ['action' => 'REQUEST_CREATED']);
        $this->assertDatabaseHas('notification_outbox', ['event' => 'REQUEST_CREATED']);

        $this->actingAs($admin)->post(route('admin.requests.approve', $leaveRequest), [
            'admin_comment' => 'Aprobado.',
        ])->assertSessionHas('status');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame(-5, LeaveBalanceMovement::where('leave_request_id', $leaveRequest->id)->where('movement_type', 'CONSUMPTION')->value('amount'));
        $this->assertDatabaseHas('notification_outbox', ['event' => 'REQUEST_APPROVED']);
    }

    public function test_approved_request_cancellation_returns_balance_after_admin_acceptance(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail();
        $admin = User::where('email', 'javierperezlopez1204@gmail.com')->firstOrFail();
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $vacations->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
        ]);

        $leaveRequest = LeaveRequest::firstOrFail();

        $this->actingAs($admin)->post(route('admin.requests.approve', $leaveRequest), [
            'admin_comment' => 'Aprobado.',
        ]);

        $this->actingAs($employee)->post(route('leave-requests.request-cancellation', $leaveRequest))
            ->assertSessionHas('status');

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING_CANCELLATION, $leaveRequest->status);

        $this->actingAs($admin)->post(route('admin.requests.accept-cancellation', $leaveRequest), [
            'admin_comment' => 'Cancelacion aceptada.',
        ])->assertSessionHas('status');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $leaveRequest->status);
        $this->assertSame(0, LeaveBalanceMovement::where('leave_request_id', $leaveRequest->id)->sum('amount'));
    }

    public function test_medical_request_stores_private_attachment_metadata(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        Storage::fake('local');
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail();
        $medical = LeaveType::where('code', 'MEDICAL')->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'attachments' => [
                UploadedFile::fake()->create('justificante.pdf', 64, 'application/pdf'),
            ],
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('leave_type_id', $medical->id)->firstOrFail();

        $this->assertSame(120, $leaveRequest->requested_units);
        $this->assertDatabaseHas('request_attachments', [
            'leave_request_id' => $leaveRequest->id,
            'original_name' => 'justificante.pdf',
            'is_medical' => true,
        ]);
    }
}
