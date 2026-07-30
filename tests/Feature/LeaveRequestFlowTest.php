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

    public function test_all_employee_request_types_receive_admin_responses(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        Storage::fake('local');
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail();
        $admin = User::where('email', 'javierperezlopez1204@gmail.com')->firstOrFail();

        $types = LeaveType::whereIn('code', ['VACATIONS', 'MEDICAL', 'PERSONAL', 'TRAINING'])
            ->get()
            ->keyBy('code');

        $requests = [];

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['VACATIONS']->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'user_comment' => 'Vacaciones familiares.',
        ])->assertRedirect();
        $requests['VACATIONS'] = LeaveRequest::where('leave_type_id', $types['VACATIONS']->id)->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['MEDICAL']->id,
            'start_date' => '2026-09-09',
            'end_date' => '2026-09-09',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'user_comment' => 'Cita medica.',
            'attachments' => [
                UploadedFile::fake()->create('certificado-medico.pdf', 64, 'application/pdf'),
            ],
        ])->assertRedirect();
        $requests['MEDICAL'] = LeaveRequest::where('leave_type_id', $types['MEDICAL']->id)->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['PERSONAL']->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'user_comment' => 'Tramite personal.',
        ])->assertRedirect();
        $requests['PERSONAL'] = LeaveRequest::where('leave_type_id', $types['PERSONAL']->id)->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['TRAINING']->id,
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-11',
            'user_comment' => 'Curso interno.',
        ])->assertRedirect();
        $requests['TRAINING'] = LeaveRequest::where('leave_type_id', $types['TRAINING']->id)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Vacaciones')
            ->assertSee('Permiso medico')
            ->assertSee('Asuntos personales')
            ->assertSee('Formacion');

        $this->actingAs($admin)->post(route('admin.requests.approve', $requests['VACATIONS']), [
            'admin_comment' => 'Aprobado por cobertura disponible.',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('admin.requests.approve', $requests['MEDICAL']), [
            'admin_comment' => 'Aprobado con soporte medico.',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('admin.requests.reject', $requests['PERSONAL']), [
            'admin_comment' => 'Rechazado por cobertura operativa.',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('admin.requests.reject', $requests['TRAINING']), [
            'admin_comment' => 'Rechazado por reprogramacion del curso.',
        ])->assertSessionHas('status');

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $requests['VACATIONS']->refresh()->status);
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $requests['MEDICAL']->refresh()->status);
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $requests['PERSONAL']->refresh()->status);
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $requests['TRAINING']->refresh()->status);

        $this->actingAs($employee)
            ->get(route('leave-requests.show', $requests['VACATIONS']))
            ->assertOk()
            ->assertSee('Aprobada')
            ->assertSee('Aprobado por cobertura disponible.');

        $this->actingAs($employee)
            ->get(route('leave-requests.show', $requests['PERSONAL']))
            ->assertOk()
            ->assertSee('Rechazada')
            ->assertSee('Rechazado por cobertura operativa.');

        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['VACATIONS']->id, 'action' => 'REQUEST_APPROVED']);
        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['MEDICAL']->id, 'action' => 'REQUEST_APPROVED']);
        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['PERSONAL']->id, 'action' => 'REQUEST_REJECTED']);
        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['TRAINING']->id, 'action' => 'REQUEST_REJECTED']);

        $this->actingAs($employee)->post(route('leave-requests.request-cancellation', $requests['VACATIONS']))
            ->assertSessionHas('status');

        $this->actingAs($admin)->post(route('admin.requests.reject-cancellation', $requests['VACATIONS']), [
            'admin_comment' => 'Se mantiene aprobada por planificacion.',
        ])->assertSessionHas('status');

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $requests['VACATIONS']->refresh()->status);
        $this->actingAs($employee)
            ->get(route('leave-requests.show', $requests['VACATIONS']))
            ->assertOk()
            ->assertSee('Aprobada')
            ->assertSee('Se mantiene aprobada por planificacion.');
        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['VACATIONS']->id, 'action' => 'CANCELLATION_REJECTED']);

        $this->actingAs($employee)->post(route('leave-requests.request-cancellation', $requests['MEDICAL']))
            ->assertSessionHas('status');

        $this->actingAs($admin)->post(route('admin.requests.accept-cancellation', $requests['MEDICAL']), [
            'admin_comment' => 'Cancelacion aceptada.',
        ])->assertSessionHas('status');

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $requests['MEDICAL']->refresh()->status);
        $this->actingAs($employee)
            ->get(route('leave-requests.show', $requests['MEDICAL']))
            ->assertOk()
            ->assertSee('Cancelada')
            ->assertSee('Cancelacion aceptada.');
        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['MEDICAL']->id, 'action' => 'CANCELLATION_ACCEPTED']);
    }

    public function test_employee_request_errors_are_reported_before_admin_review(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = User::where('email', 'empleado@n-woffu-prime.local')->firstOrFail();
        $types = LeaveType::whereIn('code', ['VACATIONS', 'MEDICAL', 'PERSONAL'])
            ->get()
            ->keyBy('code');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['VACATIONS']->id,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-07',
        ])->assertSessionHasErrors('request');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['MEDICAL']->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
        ])->assertSessionHasErrors('request');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['PERSONAL']->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
        ])->assertRedirect();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['PERSONAL']->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
        ])->assertSessionHasErrors('request');

        $this->assertSame(1, LeaveRequest::count());
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
