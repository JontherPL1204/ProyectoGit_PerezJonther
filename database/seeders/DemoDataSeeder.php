<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\EmployeeCalendarAssignment;
use App\Models\EmployeeProfile;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveAllowance;
use App\Models\LeaveBalanceMovement;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestCalculationDay;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationOutbox;
use App\Models\Organization;
use App\Models\RequestAttachment;
use App\Models\RequestEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LeaveCalculationService;
use App\Services\OrganizationDataCache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed local demo data for manual testing.
     */
    public function run(): void
    {
        $calculator = app(LeaveCalculationService::class);

        DB::transaction(function () use ($calculator): void {
            $organization = Organization::where('slug', 'n-woffu-prime')->firstOrFail();
            $admin = User::where('organization_id', $organization->id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->orderBy('id')
                ->firstOrFail();

            $departments = $this->departments($organization);
            $location = Location::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => 'Oficina demo'],
                ['timezone' => 'America/Guayaquil'],
            );
            $schedule = WorkSchedule::where('organization_id', $organization->id)->orderBy('id')->firstOrFail();
            $calendar = HolidayCalendar::where('organization_id', $organization->id)->orderBy('id')->firstOrFail();

            $this->holidays($calendar);
            $types = $this->leaveTypes($organization, $departments);

            $profiles = $this->employees($organization, $departments, $location, $schedule, $calendar);
            $this->resetDemoRequests($profiles);
            $this->allowances($organization, $profiles, $types['VACATIONS'], $admin);

            $requests = $this->requests($calculator, $organization, $profiles, $types, $admin);
            $this->notifications($requests, $admin);

            app(OrganizationDataCache::class)->forgetOrganization($organization->id);
            app(OrganizationDataCache::class)->forgetRequestData($organization->id);
        });
    }

    /**
     * @return array<string,Department>
     */
    private function departments(Organization $organization): array
    {
        $departments = [];

        foreach (['Operaciones', 'Ventas', 'Tecnologia', 'Talento Humano', 'Atencion al cliente'] as $name) {
            $departments[$name] = Department::firstOrCreate([
                'organization_id' => $organization->id,
                'name' => $name,
            ]);
        }

        return $departments;
    }

    private function holidays(HolidayCalendar $calendar): void
    {
        foreach ([
            [$this->nextMonday(22)->addDays(2), 'Feriado demo empresa'],
            [$this->nextMonday(95), 'Dia de integracion demo'],
            [CarbonImmutable::now()->startOfYear()->addMonths(10)->day(25), 'Cierre administrativo demo'],
        ] as [$date, $name]) {
            Holiday::updateOrCreate(
                ['holiday_calendar_id' => $calendar->id, 'holiday_date' => $date->toDateString()],
                ['name' => $name, 'scope' => 'company'],
            );
        }
    }

    /**
     * @param  array<string,Department>  $departments
     * @return array<string,LeaveType>
     */
    private function leaveTypes(Organization $organization, array $departments): array
    {
        $vacations = LeaveType::where('organization_id', $organization->id)->where('code', 'VACATIONS')->firstOrFail();
        $vacations->update([
            'monthly_limit_units' => null,
            'yearly_limit_units' => 15,
            'approval_level_count' => 1,
        ]);

        $medical = LeaveType::where('organization_id', $organization->id)->where('code', 'MEDICAL')->firstOrFail();
        $medical->update([
            'attachment_requirement' => 'optional',
            'monthly_limit_units' => 960,
            'yearly_limit_units' => null,
            'approval_level_count' => 1,
        ]);

        $personal = LeaveType::where('organization_id', $organization->id)->where('code', 'PERSONAL')->firstOrFail();
        $personal->update([
            'allow_half_day' => true,
            'monthly_limit_units' => 2,
            'yearly_limit_units' => 8,
            'approval_level_count' => 1,
        ]);

        $training = LeaveType::where('organization_id', $organization->id)->where('code', 'TRAINING')->firstOrFail();
        $training->update([
            'auto_approve' => true,
            'requires_approval' => false,
            'yearly_limit_units' => 10,
            'approval_level_count' => 1,
        ]);

        $ventas = LeaveType::updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'SALES_EVENT'],
            [
                'department_id' => $departments['Ventas']->id,
                'name' => 'Evento comercial',
                'unit' => 'DAYS',
                'consumes_balance' => false,
                'balance_code' => null,
                'requires_approval' => true,
                'auto_approve' => false,
                'allow_half_day' => false,
                'attachment_requirement' => 'optional',
                'is_medical' => false,
                'notice_value' => 7,
                'notice_unit' => 'days',
                'min_units' => 1,
                'max_units' => 3,
                'monthly_limit_units' => 3,
                'yearly_limit_units' => 12,
                'approval_level_count' => 1,
                'allow_retroactive' => false,
                'visible_to_employees' => true,
                'is_active' => true,
                'position' => 5,
            ],
        );

        return [
            'VACATIONS' => $vacations,
            'MEDICAL' => $medical,
            'PERSONAL' => $personal,
            'TRAINING' => $training,
            'SALES_EVENT' => $ventas,
        ];
    }

    /**
     * @param  array<string,Department>  $departments
     * @return array<string,EmployeeProfile>
     */
    private function employees(
        Organization $organization,
        array $departments,
        Location $location,
        WorkSchedule $schedule,
        HolidayCalendar $calendar,
    ): array {
        $password = env('DEMO_USER_PASSWORD', 'Demo12345!');
        $rows = [
            'ANA' => ['Ana Torres', 'ana.torres@n-woffu-prime.local', 'DEMO-001', 'Operaciones', 'user', true, false, false],
            'LUIS' => ['Luis Gomez', 'luis.gomez@n-woffu-prime.local', 'DEMO-002', 'Operaciones', 'user', true, false, false],
            'MARIA' => ['Maria Delgado', 'maria.delgado@n-woffu-prime.local', 'DEMO-003', 'Atencion al cliente', 'user', true, false, false],
            'CARLOS' => ['Carlos Ruiz', 'carlos.ruiz@n-woffu-prime.local', 'DEMO-004', 'Tecnologia', 'user', true, false, false],
            'SOFIA' => ['Sofia Navarro', 'sofia.navarro@n-woffu-prime.local', 'DEMO-005', 'Ventas', 'user', true, false, false],
            'VALERIA' => ['Valeria Admin', 'valeria.admin@n-woffu-prime.local', 'DEMO-ADM-002', 'Talento Humano', 'admin', true, true, false],
            'DIEGO' => ['Diego Inactivo', 'diego.inactivo@n-woffu-prime.local', 'DEMO-006', 'Operaciones', 'user', false, false, false],
        ];

        $profiles = [];

        foreach ($rows as $key => [$name, $email, $code, $department, $role, $active, $canManageRules, $canViewMedical]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'organization_id' => $organization->id,
                    'name' => $name,
                    'password' => Hash::make($password),
                    'role' => $role,
                    'status' => $active ? 'active' : 'inactive',
                    'can_manage_company_rules' => $canManageRules,
                    'can_view_medical_attachments' => $canViewMedical,
                    'deactivated_at' => $active ? null : now()->subDays(12),
                ],
            );

            $profile = EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $organization->id,
                    'department_id' => $departments[$department]->id,
                    'location_id' => $location->id,
                    'employee_code' => $code,
                    'hired_at' => now()->subYears(2)->startOfYear()->addDays(rand(0, 60))->toDateString(),
                    'is_active' => $active,
                ],
            );

            EmployeeScheduleAssignment::updateOrCreate(
                ['employee_profile_id' => $profile->id, 'work_schedule_id' => $schedule->id, 'valid_from' => now()->startOfYear()->toDateString()],
                ['valid_until' => null],
            );

            EmployeeCalendarAssignment::updateOrCreate(
                ['employee_profile_id' => $profile->id, 'holiday_calendar_id' => $calendar->id, 'valid_from' => now()->startOfYear()->toDateString()],
                ['valid_until' => null],
            );

            $profiles[$key] = $profile->fresh(['user']);
        }

        return $profiles;
    }

    /**
     * @param  array<string,EmployeeProfile>  $profiles
     */
    private function resetDemoRequests(array $profiles): void
    {
        $profileIds = collect($profiles)->pluck('id')->all();
        $requestIds = LeaveRequest::withTrashed()
            ->whereIn('employee_profile_id', $profileIds)
            ->pluck('id');

        if ($requestIds->isNotEmpty()) {
            NotificationOutbox::whereIn('leave_request_id', $requestIds)->delete();
            RequestEvent::whereIn('leave_request_id', $requestIds)->delete();
            RequestAttachment::withTrashed()->whereIn('leave_request_id', $requestIds)->forceDelete();
            LeaveRequestCalculationDay::whereIn('leave_request_id', $requestIds)->delete();
            LeaveBalanceMovement::whereIn('leave_request_id', $requestIds)->delete();
            LeaveRequest::withTrashed()->whereIn('id', $requestIds)->forceDelete();
        }

        $allowanceIds = LeaveAllowance::whereIn('employee_profile_id', $profileIds)->pluck('id');

        if ($allowanceIds->isNotEmpty()) {
            LeaveBalanceMovement::whereIn('leave_allowance_id', $allowanceIds)->delete();
        }
    }

    /**
     * @param  array<string,EmployeeProfile>  $profiles
     */
    private function allowances(Organization $organization, array $profiles, LeaveType $vacations, User $admin): void
    {
        foreach ($profiles as $profile) {
            $allowance = LeaveAllowance::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'employee_profile_id' => $profile->id,
                    'balance_code' => 'VACATIONS',
                    'period_start' => now()->startOfYear()->toDateString(),
                ],
                [
                    'leave_type_id' => $vacations->id,
                    'period_end' => now()->endOfYear()->toDateString(),
                    'assigned_units' => 15,
                ],
            );

            LeaveBalanceMovement::create([
                'organization_id' => $organization->id,
                'leave_allowance_id' => $allowance->id,
                'movement_type' => 'ALLOCATION',
                'amount' => 15,
                'idempotency_key' => 'demo-allocation-'.$profile->id.'-'.now()->year,
                'reason' => 'Asignacion anual demo',
                'created_by' => $admin->id,
                'created_at' => now()->startOfYear()->addHour(),
            ]);
        }
    }

    /**
     * @param  array<string,EmployeeProfile>  $profiles
     * @param  array<string,LeaveType>  $types
     * @return list<LeaveRequest>
     */
    private function requests(
        LeaveCalculationService $calculator,
        Organization $organization,
        array $profiles,
        array $types,
        User $admin,
    ): array {
        $overlapStart = $this->nextMonday(40);
        $approvedStart = $this->nextMonday(60);
        $cancellationStart = $this->nextMonday(80);
        $salesStart = $this->nextMonday(25);
        $medicalDay = $this->nextBusinessDay(7);
        $medicalRangeStart = $this->nextMonday(14);
        $trainingStart = $this->nextMonday(18);
        $pastRejected = $this->previousBusinessDay(12);
        $pastCancelled = $this->previousBusinessDay(35);

        return [
            $this->makeRequest($calculator, $profiles['ANA'], $types['VACATIONS'], $admin, [
                'status' => LeaveRequest::STATUS_PENDING,
                'start_date' => $overlapStart,
                'end_date' => $overlapStart->addDays(4),
                'user_comment' => 'Vacaciones familiares planificadas con anticipacion.',
            ]),
            $this->makeRequest($calculator, $profiles['LUIS'], $types['VACATIONS'], $admin, [
                'status' => LeaveRequest::STATUS_PENDING,
                'start_date' => $overlapStart->addDay(),
                'end_date' => $overlapStart->addDays(5),
                'user_comment' => 'Viaje reservado. Esta solicitud se solapa para probar alerta del admin.',
            ]),
            $this->makeRequest($calculator, $profiles['MARIA'], $types['VACATIONS'], $admin, [
                'status' => LeaveRequest::STATUS_APPROVED,
                'start_date' => $approvedStart,
                'end_date' => $approvedStart->addDays(4),
                'user_comment' => 'Vacaciones aprobadas de prueba.',
                'admin_comment' => 'Aprobada para validar calendario y reportes.',
            ]),
            $this->makeRequest($calculator, $profiles['CARLOS'], $types['VACATIONS'], $admin, [
                'status' => LeaveRequest::STATUS_PENDING_CANCELLATION,
                'start_date' => $cancellationStart,
                'end_date' => $cancellationStart->addDays(4),
                'user_comment' => 'Vacaciones aprobadas inicialmente.',
                'admin_comment' => 'Pendiente revisar cancelacion.',
            ]),
            $this->makeRequest($calculator, $profiles['SOFIA'], $types['VACATIONS'], $admin, [
                'status' => LeaveRequest::STATUS_CANCELLED,
                'start_date' => $pastCancelled,
                'end_date' => $pastCancelled,
                'user_comment' => 'Solicitud cancelada para probar historial.',
                'admin_comment' => 'Cancelacion aceptada.',
            ]),
            $this->makeRequest($calculator, $profiles['ANA'], $types['PERSONAL'], $admin, [
                'status' => LeaveRequest::STATUS_REJECTED,
                'start_date' => $pastRejected,
                'end_date' => $pastRejected,
                'user_comment' => 'Permiso personal fuera de politica.',
                'admin_comment' => 'Rechazada porque ya habia cobertura minima ese dia.',
            ]),
            $this->makeRequest($calculator, $profiles['MARIA'], $types['MEDICAL'], $admin, [
                'status' => LeaveRequest::STATUS_PENDING,
                'unit' => 'MINUTES',
                'start_date' => $medicalDay,
                'end_date' => $medicalDay,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'user_comment' => 'Consulta medica por horas.',
                'attachment_status' => 'pending',
            ]),
            $this->makeRequest($calculator, $profiles['LUIS'], $types['MEDICAL'], $admin, [
                'status' => LeaveRequest::STATUS_APPROVED,
                'unit' => 'DAYS',
                'start_date' => $medicalRangeStart,
                'end_date' => $medicalRangeStart->addDay(),
                'user_comment' => 'Reposo medico por dias.',
                'admin_comment' => 'Aprobado con justificante recibido.',
                'attachment_status' => 'reviewed',
            ]),
            $this->makeRequest($calculator, $profiles['CARLOS'], $types['TRAINING'], $admin, [
                'status' => LeaveRequest::STATUS_APPROVED,
                'start_date' => $trainingStart,
                'end_date' => $trainingStart->addDays(2),
                'user_comment' => 'Formacion externa autoaprobable.',
                'admin_comment' => 'Aprobacion automatica por regla.',
            ]),
            $this->makeRequest($calculator, $profiles['SOFIA'], $types['SALES_EVENT'], $admin, [
                'status' => LeaveRequest::STATUS_PENDING,
                'start_date' => $salesStart,
                'end_date' => $salesStart->addDay(),
                'user_comment' => 'Evento comercial para validar regla por departamento.',
                'attachment_status' => 'received',
            ]),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function makeRequest(
        LeaveCalculationService $calculator,
        EmployeeProfile $profile,
        LeaveType $type,
        User $admin,
        array $data,
    ): LeaveRequest {
        $unit = $data['unit'] ?? $type->unit;
        $startDate = $this->asDate($data['start_date']);
        $endDate = $this->asDate($data['end_date']);
        $typeForCalculation = clone $type;
        $typeForCalculation->unit = $unit;

        $calculation = $calculator->calculate(
            $profile,
            $typeForCalculation,
            $startDate,
            $endDate,
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
        );

        $status = $data['status'] ?? LeaveRequest::STATUS_PENDING;
        $createdAt = now()->subDays(rand(1, 24))->subHours(rand(1, 6));

        $request = LeaveRequest::create([
            'organization_id' => $profile->organization_id,
            'employee_profile_id' => $profile->id,
            'leave_type_id' => $type->id,
            'unit' => $unit,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'requested_units' => $calculation['units'],
            'status' => $status,
            'user_comment' => $data['user_comment'] ?? null,
            'admin_comment' => $data['admin_comment'] ?? null,
            'version' => match ($status) {
                LeaveRequest::STATUS_PENDING => 1,
                LeaveRequest::STATUS_PENDING_CANCELLATION => 3,
                default => 2,
            },
            'requested_cancel_at' => $status === LeaveRequest::STATUS_PENDING_CANCELLATION ? now()->subDay() : null,
            'cancelled_at' => $status === LeaveRequest::STATUS_CANCELLED ? now()->subDays(2) : null,
        ]);
        $request->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        foreach ($calculation['rows'] as $row) {
            $request->calculationDays()->create($row);
        }

        $this->events($request, $profile->user, $admin, $status, $data['user_comment'] ?? null, $data['admin_comment'] ?? null, $createdAt);

        if ($type->consumes_balance && in_array($status, [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING_CANCELLATION, LeaveRequest::STATUS_CANCELLED], true)) {
            $this->balanceMovement($request, 'CONSUMPTION', -1 * $request->requested_units, 'Consumo demo por solicitud aprobada', $admin);
        }

        if ($type->consumes_balance && $status === LeaveRequest::STATUS_CANCELLED) {
            $this->balanceMovement($request, 'RETURN', $request->requested_units, 'Devolucion demo por cancelacion aceptada', $admin);
        }

        if (isset($data['attachment_status'])) {
            $this->attachment($request, $profile->user, $admin, $data['attachment_status']);
        }

        return $request->fresh(['employeeProfile.user', 'leaveType']);
    }

    private function events(
        LeaveRequest $request,
        User $employee,
        User $admin,
        string $status,
        ?string $userComment,
        ?string $adminComment,
        CarbonInterface $createdAt,
    ): void {
        RequestEvent::create([
            'organization_id' => $request->organization_id,
            'leave_request_id' => $request->id,
            'actor_user_id' => $employee->id,
            'action' => 'REQUEST_CREATED',
            'previous_status' => null,
            'new_status' => LeaveRequest::STATUS_PENDING,
            'comment' => $userComment,
            'metadata' => ['demo' => true, 'requested_units' => $request->requested_units],
            'created_at' => $createdAt,
        ]);

        if ($status === LeaveRequest::STATUS_PENDING) {
            return;
        }

        if (in_array($status, [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING_CANCELLATION, LeaveRequest::STATUS_CANCELLED], true)) {
            RequestEvent::create([
                'organization_id' => $request->organization_id,
                'leave_request_id' => $request->id,
                'actor_user_id' => $admin->id,
                'action' => 'REQUEST_APPROVED',
                'previous_status' => LeaveRequest::STATUS_PENDING,
                'new_status' => LeaveRequest::STATUS_APPROVED,
                'comment' => $adminComment,
                'metadata' => ['demo' => true],
                'created_at' => $createdAt->addHours(3),
            ]);
        }

        if ($status === LeaveRequest::STATUS_REJECTED) {
            RequestEvent::create([
                'organization_id' => $request->organization_id,
                'leave_request_id' => $request->id,
                'actor_user_id' => $admin->id,
                'action' => 'REQUEST_REJECTED',
                'previous_status' => LeaveRequest::STATUS_PENDING,
                'new_status' => LeaveRequest::STATUS_REJECTED,
                'comment' => $adminComment,
                'metadata' => ['demo' => true],
                'created_at' => $createdAt->addHours(3),
            ]);
        }

        if (in_array($status, [LeaveRequest::STATUS_PENDING_CANCELLATION, LeaveRequest::STATUS_CANCELLED], true)) {
            RequestEvent::create([
                'organization_id' => $request->organization_id,
                'leave_request_id' => $request->id,
                'actor_user_id' => $employee->id,
                'action' => 'CANCELLATION_REQUESTED',
                'previous_status' => LeaveRequest::STATUS_APPROVED,
                'new_status' => LeaveRequest::STATUS_PENDING_CANCELLATION,
                'comment' => 'Solicitud demo de cancelacion.',
                'metadata' => ['demo' => true],
                'created_at' => $createdAt->addDay(),
            ]);
        }

        if ($status === LeaveRequest::STATUS_CANCELLED) {
            RequestEvent::create([
                'organization_id' => $request->organization_id,
                'leave_request_id' => $request->id,
                'actor_user_id' => $admin->id,
                'action' => 'CANCELLATION_ACCEPTED',
                'previous_status' => LeaveRequest::STATUS_PENDING_CANCELLATION,
                'new_status' => LeaveRequest::STATUS_CANCELLED,
                'comment' => $adminComment,
                'metadata' => ['demo' => true],
                'created_at' => $createdAt->addDays(2),
            ]);
        }
    }

    private function balanceMovement(LeaveRequest $request, string $type, int $amount, string $reason, User $admin): void
    {
        $allowance = LeaveAllowance::where('employee_profile_id', $request->employee_profile_id)
            ->where('balance_code', 'VACATIONS')
            ->whereDate('period_start', '<=', $request->start_date)
            ->whereDate('period_end', '>=', $request->start_date)
            ->firstOrFail();

        LeaveBalanceMovement::create([
            'organization_id' => $request->organization_id,
            'leave_allowance_id' => $allowance->id,
            'leave_request_id' => $request->id,
            'movement_type' => $type,
            'amount' => $amount,
            'idempotency_key' => 'demo-'.Str::lower($type).'-'.$request->id,
            'reason' => $reason,
            'created_by' => $admin->id,
            'created_at' => now()->subDays(rand(1, 10)),
        ]);
    }

    private function attachment(LeaveRequest $request, User $user, User $admin, string $status): void
    {
        $content = "%PDF-1.4\n% N-Woffu Prime demo file\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $path = 'demo/justificantes/solicitud-'.$request->id.'-'.$status.'.pdf';

        Storage::disk('local')->put($path, $content);

        RequestAttachment::create([
            'organization_id' => $request->organization_id,
            'leave_request_id' => $request->id,
            'uploaded_by' => $user->id,
            'original_name' => 'justificante-demo-'.$request->id.'.pdf',
            'stored_name' => basename($path),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'is_medical' => $request->leaveType?->is_medical ?? false,
            'justification_status' => $status,
            'reviewed_at' => $status === 'reviewed' ? now()->subHours(5) : null,
            'reviewed_by' => $status === 'reviewed' ? $admin->id : null,
            'checksum' => hash('sha256', $content),
        ]);
    }

    /**
     * @param  list<LeaveRequest>  $requests
     */
    private function notifications(array $requests, User $admin): void
    {
        $admins = User::where('organization_id', $admin->organization_id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->get();

        foreach ($requests as $request) {
            $employeeEmail = $request->employeeProfile?->user?->email ?? 'empleado@n-woffu-prime.local';

            match ($request->status) {
                LeaveRequest::STATUS_PENDING => $this->notifyAdmins($admins, $request, 'REQUEST_CREATED', 'pending'),
                LeaveRequest::STATUS_APPROVED => $this->notifyEmployee($request, 'REQUEST_APPROVED', $employeeEmail, 'sent'),
                LeaveRequest::STATUS_REJECTED => $this->notifyEmployee($request, 'REQUEST_REJECTED', $employeeEmail, 'failed'),
                LeaveRequest::STATUS_PENDING_CANCELLATION => $this->notifyAdmins($admins, $request, 'CANCELLATION_REQUESTED', 'pending'),
                LeaveRequest::STATUS_CANCELLED => $this->notifyEmployee($request, 'CANCELLATION_ACCEPTED', $employeeEmail, 'sent'),
                default => null,
            };
        }
    }

    private function notifyAdmins($admins, LeaveRequest $request, string $event, string $status): void
    {
        foreach ($admins as $admin) {
            $this->notification($request, $event, $admin->email, $status);
        }
    }

    private function notifyEmployee(LeaveRequest $request, string $event, string $email, string $status): void
    {
        $this->notification($request, $event, $email, $status);
    }

    private function notification(LeaveRequest $request, string $event, string $email, string $status): void
    {
        NotificationOutbox::create([
            'organization_id' => $request->organization_id,
            'leave_request_id' => $request->id,
            'event' => $event,
            'recipient_email' => $email,
            'subject' => '[N-Woffu Prime] Demo solicitud #'.$request->id.' - '.$request->statusLabel(),
            'body' => $this->demoNotificationBody($request, $event),
            'status' => $status,
            'attempts' => $status === 'failed' ? 3 : ($status === 'sent' ? 1 : 0),
            'available_at' => $status === 'pending' ? now() : null,
            'sent_at' => $status === 'sent' ? now()->subHours(rand(1, 12)) : null,
            'last_error' => $status === 'failed' ? 'SMTP demo: autenticacion pendiente o clave de aplicacion no configurada.' : null,
        ]);
    }

    private function demoNotificationBody(LeaveRequest $request, string $event): string
    {
        return match ($event) {
            'REQUEST_CREATED' => $request->employeeProfile->user->name.' envio una solicitud de '.$request->leaveType->name.'. Revisa reglas, cobertura del equipo y solapamientos antes de resolver.',
            'REQUEST_APPROVED' => 'La solicitud de '.$request->leaveType->name.' fue aprobada. El detalle ya esta disponible para el empleado dentro de N-Woffu Prime.',
            'REQUEST_REJECTED' => 'La solicitud de '.$request->leaveType->name.' fue rechazada. El empleado puede revisar el comentario de revision dentro de la aplicacion.',
            'CANCELLATION_REQUESTED' => $request->employeeProfile->user->name.' solicito cancelar una ausencia aprobada. Revisa si corresponde devolver saldo.',
            'CANCELLATION_ACCEPTED' => 'La cancelacion fue aceptada y la solicitud quedo actualizada en el historial.',
            'CANCELLATION_REJECTED' => 'La cancelacion fue rechazada y la ausencia mantiene su aprobacion.',
            default => 'Hay una actualizacion de solicitud disponible en N-Woffu Prime.',
        };
    }

    private function nextMonday(int $minimumDaysAhead): CarbonImmutable
    {
        $date = CarbonImmutable::now()->startOfDay()->addDays($minimumDaysAhead);

        while ($date->isoWeekday() !== 1) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function nextBusinessDay(int $minimumDaysAhead): CarbonImmutable
    {
        $date = CarbonImmutable::now()->startOfDay()->addDays($minimumDaysAhead);

        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function previousBusinessDay(int $minimumDaysBack): CarbonImmutable
    {
        $date = CarbonImmutable::now()->startOfDay()->subDays($minimumDaysBack);

        while ($date->isWeekend()) {
            $date = $date->subDay();
        }

        return $date;
    }

    private function asDate(mixed $value): string
    {
        return $value instanceof CarbonImmutable
            ? $value->toDateString()
            : CarbonImmutable::parse($value)->toDateString();
    }
}
