<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveBalanceMovement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NotificationOutbox;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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

        $employee = $this->employeeUser();
        $admin = $this->adminUser();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('calendar'))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('history'))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('admin.reports'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('admin.management.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('admin.rules.edit'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('calendar'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('history'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.management.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.rules.edit'))
            ->assertOk();
    }

    public function test_employee_can_request_vacation_and_admin_can_approve_it(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
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

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Solicitudes pendientes')
            ->assertSee('Empleado Demo')
            ->assertSee('Vacaciones');

        $this->actingAs($admin)->post(route('admin.requests.approve', $leaveRequest), [
            'admin_comment' => 'Aprobado.',
        ])->assertSessionHas('status');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame(-5, LeaveBalanceMovement::where('leave_request_id', $leaveRequest->id)->where('movement_type', 'CONSUMPTION')->value('amount'));
        $this->assertDatabaseHas('notification_outbox', [
            'event' => 'REQUEST_APPROVED',
            'recipient_email' => $employee->email,
            'status' => 'sent',
        ]);
    }

    public function test_all_employee_request_types_receive_admin_responses(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        Storage::fake('local');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();

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
            'duration_unit' => 'MINUTES',
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

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['estado' => 'aprobadas']))
            ->assertOk()
            ->assertSee('Vacaciones')
            ->assertSee('Permiso medico')
            ->assertDontSee('Asuntos personales &middot;', false);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['estado' => 'rechazadas']))
            ->assertOk()
            ->assertSee('Asuntos personales')
            ->assertSee('Formacion');

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
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['estado' => 'canceladas']))
            ->assertOk()
            ->assertSee('Permiso medico');
        $this->assertDatabaseHas('request_events', ['leave_request_id' => $requests['MEDICAL']->id, 'action' => 'CANCELLATION_ACCEPTED']);
    }

    public function test_calendar_shows_absences_and_holidays_for_employee_and_admin(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();
        $calendar = HolidayCalendar::firstOrFail();

        Holiday::create([
            'holiday_calendar_id' => $calendar->id,
            'holiday_date' => '2026-09-11',
            'name' => 'Festivo de prueba',
            'scope' => 'company',
        ]);

        LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $vacations->id,
            'unit' => 'DAYS',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'requested_units' => 3,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($employee)
            ->get(route('calendar', ['mes' => '2026-09']))
            ->assertOk()
            ->assertSee('Septiembre 2026')
            ->assertSee('Vacaciones')
            ->assertSee('Aprobada')
            ->assertSee('Festivo de prueba');

        $this->actingAs($admin)
            ->get(route('calendar', ['mes' => '2026-09']))
            ->assertOk()
            ->assertSee('Empleado Demo')
            ->assertSee('Festivo de prueba');
    }

    public function test_admin_can_filter_requests_and_see_overlap_warnings(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();
        $training = LeaveType::where('code', 'TRAINING')->firstOrFail();

        LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $vacations->id,
            'unit' => 'DAYS',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'requested_units' => 3,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        LeaveRequest::create([
            'organization_id' => $admin->organization_id,
            'employee_profile_id' => $admin->employeeProfile->id,
            'leave_type_id' => $vacations->id,
            'unit' => 'DAYS',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-11',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $training->id,
            'unit' => 'DAYS',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', [
                'estado' => 'pendientes',
                'empleado' => $employee->employeeProfile->id,
                'tipo' => $vacations->id,
                'desde' => '2026-09-01',
                'hasta' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('Empleado Demo')
            ->assertSee('Vacaciones')
            ->assertSee('Coincide con otras ausencias')
            ->assertSee('Javier Perez')
            ->assertDontSee('Formacion &middot;', false);
    }

    public function test_history_reports_and_notification_resend_work(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
        $personal = LeaveType::where('code', 'PERSONAL')->firstOrFail();
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();

        $personalRequest = LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $personal->id,
            'unit' => 'DAYS',
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_REJECTED,
            'user_comment' => 'Tramite especial',
            'admin_comment' => 'Sin cobertura',
        ]);

        LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $vacations->id,
            'unit' => 'DAYS',
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-25',
            'requested_units' => 5,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($employee)
            ->get(route('history', ['q' => 'especial', 'estado' => LeaveRequest::STATUS_REJECTED]))
            ->assertOk()
            ->assertSee('Tramite especial')
            ->assertSee('Rechazada');

        $this->actingAs($admin)
            ->get(route('history', ['empleado' => $employee->employeeProfile->id, 'tipo' => $vacations->id]))
            ->assertOk()
            ->assertSee('Vacaciones')
            ->assertSee('Empleado Demo');

        $this->actingAs($admin)
            ->get(route('admin.reports', ['mes' => 9, 'anio' => 2026]))
            ->assertOk()
            ->assertSee('Balance mensual')
            ->assertSee('Vacaciones usadas')
            ->assertSee('5');

        $notification = NotificationOutbox::create([
            'organization_id' => $employee->organization_id,
            'leave_request_id' => $personalRequest->id,
            'event' => 'REQUEST_REJECTED',
            'recipient_email' => $employee->email,
            'subject' => '[N-Woffu Prime] Prueba',
            'body' => 'Correo de prueba',
            'status' => 'failed',
            'attempts' => 3,
            'available_at' => now(),
            'last_error' => 'SMTP temporal',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', ['estado' => 'failed']))
            ->assertOk()
            ->assertSee('No enviado')
            ->assertSee('SMTP temporal');

        Mail::fake();

        $this->actingAs($admin)
            ->post(route('admin.notifications.resend', $notification))
            ->assertSessionHas('status');

        $this->assertSame('sent', $notification->refresh()->status);
    }

    public function test_admin_can_grant_and_revoke_admin_permissions(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Equipo registrado')
            ->assertSee($employee->email);

        $this->actingAs($admin)
            ->post(route('admin.users.promote'), [
                'email' => 'EMPLEADO@n-woffu-prime.local',
                'can_manage_company_rules' => '1',
            ])
            ->assertSessionHas('status');

        $employee->refresh();

        $this->assertTrue($employee->isAdmin());
        $this->assertTrue($employee->can_manage_company_rules);
        $this->assertFalse($employee->can_view_medical_attachments);
        $this->assertDatabaseHas('rule_change_events', [
            'entity_type' => 'users',
            'entity_id' => $employee->id,
            'field_name' => 'role',
            'new_value' => 'admin',
        ]);

        $this->actingAs($employee)
            ->get(route('admin.rules.edit'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.users.update', $employee), [
                'is_admin' => '1',
                'can_view_medical_attachments' => '1',
            ])
            ->assertSessionHas('status');

        $employee->refresh();

        $this->assertTrue($employee->isAdmin());
        $this->assertFalse($employee->can_manage_company_rules);
        $this->assertTrue($employee->can_view_medical_attachments);

        $this->actingAs($admin)
            ->post(route('admin.users.update', $employee), [])
            ->assertSessionHas('status');

        $employee->refresh();

        $this->assertFalse($employee->isAdmin());
        $this->assertFalse($employee->can_manage_company_rules);
        $this->assertFalse($employee->can_view_medical_attachments);

        $this->actingAs($admin)
            ->post(route('admin.users.update', $admin), [])
            ->assertSessionHasErrors('is_admin');
    }

    public function test_employee_request_errors_are_reported_before_admin_review(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $types = LeaveType::whereIn('code', ['VACATIONS', 'MEDICAL', 'PERSONAL'])
            ->get()
            ->keyBy('code');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['VACATIONS']->id,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-07',
        ])->assertSessionHasErrors('request');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['PERSONAL']->id,
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-15',
        ])->assertSessionHasErrors([
            'end_date' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ]);

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $types['MEDICAL']->id,
            'duration_unit' => 'MINUTES',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
        ])->assertSessionHasErrors('start_time');

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

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
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

        $employee = $this->employeeUser();
        $medical = LeaveType::where('code', 'MEDICAL')->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'duration_unit' => 'MINUTES',
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

    public function test_medical_request_can_be_requested_by_days(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $medical = LeaveType::where('code', 'MEDICAL')->firstOrFail();

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'duration_unit' => 'DAYS',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-09',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'user_comment' => 'Reposo medico.',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('leave_type_id', $medical->id)->firstOrFail();

        $this->assertSame('DAYS', $leaveRequest->unit);
        $this->assertSame(3, $leaveRequest->requested_units);
        $this->assertNull($leaveRequest->start_time);
        $this->assertNull($leaveRequest->end_time);
    }

    public function test_advanced_rules_enforce_limits_auto_approval_and_justification_status(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        Storage::fake('local');
        Mail::fake();
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
        $medical = LeaveType::where('code', 'MEDICAL')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.rules.update'), [
            'annual_vacation_days' => 15,
            'vacation_notice_days' => 30,
            'medical_documents_retention_policy' => 'retain',
            'pending_requests_reserve_balance' => '1',
            'admin_can_view_medical_attachments' => '1',
            'medical_attachment_audit_required' => '1',
            'approved_request_requires_cancel_flow' => '1',
            'prorate_vacations' => '1',
            'carry_over_unused_balance' => '1',
            'change_comment' => 'Regla medica configurable para pruebas.',
            'leave_types' => [
                $medical->id => [
                    'name' => 'Permiso medico',
                    'department_id' => (string) $employee->employeeProfile->department_id,
                    'is_active' => '1',
                    'visible_to_employees' => '1',
                    'requires_approval' => '1',
                    'auto_approve' => '1',
                    'allow_half_day' => '1',
                    'allow_retroactive' => '0',
                    'attachment_requirement' => 'required',
                    'notice_value' => '0',
                    'min_units' => '30',
                    'max_units' => '480',
                    'monthly_limit_units' => '180',
                    'yearly_limit_units' => '500',
                    'approval_level_count' => '2',
                ],
            ],
        ])->assertSessionHas('status');

        $medical->refresh();

        $this->assertTrue($medical->auto_approve);
        $this->assertTrue($medical->allow_half_day);
        $this->assertSame(180, $medical->monthly_limit_units);
        $this->assertSame(2, $medical->approval_level_count);

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'duration_unit' => 'MINUTES',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertSessionHasErrors('attachments');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'duration_unit' => 'MINUTES',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'attachments' => [
                UploadedFile::fake()->create('justificante-1.pdf', 64, 'application/pdf'),
            ],
        ])->assertSessionHas('status');

        $firstRequest = LeaveRequest::where('leave_type_id', $medical->id)->firstOrFail();

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $firstRequest->status);
        $this->assertDatabaseHas('notification_outbox', [
            'leave_request_id' => $firstRequest->id,
            'event' => 'REQUEST_APPROVED',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('request_attachments', [
            'leave_request_id' => $firstRequest->id,
            'original_name' => 'justificante-1.pdf',
            'justification_status' => 'received',
        ]);

        $attachment = $firstRequest->attachments()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('attachments.review', $attachment))
            ->assertSessionHas('status');

        $this->assertSame('reviewed', $attachment->refresh()->justification_status);

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'duration_unit' => 'MINUTES',
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-08',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'attachments' => [
                UploadedFile::fake()->create('justificante-2.pdf', 64, 'application/pdf'),
            ],
        ])->assertSessionHas('status');

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $medical->id,
            'duration_unit' => 'MINUTES',
            'start_date' => '2026-09-09',
            'end_date' => '2026-09-09',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'attachments' => [
                UploadedFile::fake()->create('justificante-3.pdf', 64, 'application/pdf'),
            ],
        ])->assertSessionHasErrors('request');
    }

    public function test_admin_can_edit_active_and_inactive_leave_type_rules(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
        $medical = LeaveType::where('code', 'MEDICAL')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.rules.update'), [
            'annual_vacation_days' => 15,
            'vacation_notice_days' => 30,
            'medical_documents_retention_policy' => 'retain',
            'pending_requests_reserve_balance' => '1',
            'admin_can_view_medical_attachments' => '1',
            'medical_attachment_audit_required' => '1',
            'approved_request_requires_cancel_flow' => '1',
            'prorate_vacations' => '1',
            'carry_over_unused_balance' => '1',
            'change_comment' => 'Se desactiva temporalmente permiso medico.',
            'leave_types' => [
                $medical->id => [
                    'name' => 'Permiso medico temporal',
                    'department_id' => '',
                    'is_active' => '0',
                    'visible_to_employees' => '0',
                    'requires_approval' => '1',
                    'auto_approve' => '0',
                    'allow_half_day' => '0',
                    'allow_retroactive' => '1',
                    'attachment_requirement' => 'required',
                    'notice_value' => '2',
                    'min_units' => '30',
                    'max_units' => '480',
                    'monthly_limit_units' => '',
                    'yearly_limit_units' => '',
                    'approval_level_count' => '1',
                ],
            ],
        ])->assertSessionHas('status');

        $medical->refresh();

        $this->assertSame('Permiso medico temporal', $medical->name);
        $this->assertFalse($medical->is_active);
        $this->assertFalse($medical->visible_to_employees);
        $this->assertTrue($medical->allow_retroactive);
        $this->assertSame('required', $medical->attachment_requirement);
        $this->assertSame(2, $medical->notice_value);
        $this->assertDatabaseHas('rule_change_events', [
            'entity_type' => 'leave_types',
            'entity_id' => $medical->id,
            'field_name' => 'is_active',
            'new_value' => 'false',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rules.edit'))
            ->assertOk()
            ->assertSee('Reglas de ausencia')
            ->assertSee('Inactiva');

        $this->actingAs($employee)
            ->get(route('leave-requests.create'))
            ->assertOk()
            ->assertDontSee('Permiso medico temporal');
    }

    public function test_admin_can_disable_notification_rules(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        $this->seed();

        $employee = $this->employeeUser();
        $admin = $this->adminUser();
        $vacations = LeaveType::where('code', 'VACATIONS')->firstOrFail();
        $rule = NotificationRule::where('event', 'REQUEST_CREATED')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.rules.edit'))
            ->assertOk()
            ->assertSee('Reglas de notificacion')
            ->assertSee('Nueva solicitud')
            ->assertSee('class="status-select', false)
            ->assertSee('name="notification_rules['.$rule->id.'][is_active]"', false);

        $this->actingAs($admin)->post(route('admin.rules.update'), [
            'annual_vacation_days' => 15,
            'vacation_notice_days' => 30,
            'medical_documents_retention_policy' => 'retain',
            'pending_requests_reserve_balance' => '1',
            'admin_can_view_medical_attachments' => '1',
            'medical_attachment_audit_required' => '1',
            'approved_request_requires_cancel_flow' => '1',
            'prorate_vacations' => '1',
            'carry_over_unused_balance' => '1',
            'change_comment' => 'Se apaga correo de nueva solicitud temporalmente.',
            'notification_rules' => [
                $rule->id => ['is_active' => '0'],
            ],
        ])->assertSessionHas('status');

        $this->assertFalse($rule->refresh()->is_active);
        $this->assertDatabaseHas('rule_change_events', [
            'entity_type' => 'notification_rules',
            'entity_id' => $rule->id,
            'field_name' => 'is_active',
            'new_value' => 'false',
        ]);

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $vacations->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('employee_profile_id', $employee->employeeProfile->id)->firstOrFail();

        $this->assertDatabaseMissing('notification_outbox', [
            'leave_request_id' => $leaveRequest->id,
            'event' => 'REQUEST_CREATED',
        ]);
    }

    public function test_admin_can_add_and_delete_custom_leave_type_rules(): void
    {
        $this->seed();

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.rules.leave-types.store'), [
                'name' => 'Permiso por mudanza',
                'unit' => 'DAYS',
                'attachment_requirement' => 'optional',
                'department_id' => '',
            ])
            ->assertSessionHas('status');

        $leaveType = LeaveType::where('name', 'Permiso por mudanza')->firstOrFail();

        $this->assertFalse($leaveType->is_system);
        $this->assertSame('DAYS', $leaveType->unit);

        $this->actingAs($admin)
            ->get(route('admin.rules.edit'))
            ->assertOk()
            ->assertSee('Agregar regla')
            ->assertSee('Permiso por mudanza')
            ->assertDontSee('Limite mensual')
            ->assertDontSee('Niveles aprobacion');

        $this->actingAs($admin)
            ->post(route('admin.rules.leave-types.destroy', $leaveType))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('leave_types', ['id' => $leaveType->id]);
    }

    private function employeeUser(): User
    {
        return User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
    }

    private function adminUser(): User
    {
        return User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
    }
}
