<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\EmployeeCalendarAssignment;
use App\Models\EmployeeProfile;
use App\Models\EmployeeScheduleAssignment;
use App\Models\HolidayCalendar;
use App\Models\LeaveAllowance;
use App\Models\LeaveBalanceMovement;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationRule;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['slug' => 'n-woffu-prime'],
            [
                'name' => 'N-Woffu Prime',
                'mode' => 'internal',
                'timezone' => 'America/Guayaquil',
                'locale' => 'es',
            ],
        );

        CompanySetting::updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'annual_vacation_days' => 15,
                'vacation_notice_days' => 30,
                'allow_negative_balance' => false,
                'pending_requests_reserve_balance' => true,
                'default_notification_channel' => 'email',
                'admin_can_view_medical_attachments' => true,
                'medical_attachment_audit_required' => true,
                'approved_request_requires_cancel_flow' => true,
                'prorate_vacations' => true,
                'carry_over_unused_balance' => true,
                'medical_documents_retention_policy' => 'retain',
                'period_start_month' => 1,
                'period_start_day' => 1,
            ],
        );

        $department = Department::firstOrCreate([
            'organization_id' => $organization->id,
            'name' => 'Operaciones',
        ]);

        $location = Location::firstOrCreate([
            'organization_id' => $organization->id,
            'name' => 'Oficina principal',
        ], [
            'timezone' => 'America/Guayaquil',
        ]);

        $admin = User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local')],
            [
                'organization_id' => $organization->id,
                'name' => env('SEED_ADMIN_NAME', 'Javier Perez'),
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', Str::password(24))),
                'role' => 'admin',
                'status' => 'active',
                'can_manage_company_rules' => true,
                'can_view_medical_attachments' => true,
            ],
        );

        $employee = User::updateOrCreate(
            ['email' => env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local')],
            [
                'organization_id' => $organization->id,
                'name' => env('SEED_EMPLOYEE_NAME', 'Empleado Demo'),
                'password' => Hash::make(env('SEED_EMPLOYEE_PASSWORD', Str::password(24))),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        foreach ([$admin, $employee] as $index => $user) {
            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $organization->id,
                    'department_id' => $department->id,
                    'location_id' => $location->id,
                    'employee_code' => $index === 0 ? 'ADM-001' : 'EMP-001',
                    'hired_at' => now()->startOfYear()->toDateString(),
                    'is_active' => true,
                ],
            );
        }

        $schedule = WorkSchedule::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Lunes a viernes'],
            ['is_active' => true],
        );

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            WorkScheduleDay::updateOrCreate(
                ['work_schedule_id' => $schedule->id, 'weekday' => $weekday],
                [
                    'is_working_day' => $weekday <= 5,
                    'work_minutes' => $weekday <= 5 ? 480 : 0,
                ],
            );
        }

        $calendar = HolidayCalendar::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Calendario empresa'],
            ['is_active' => true],
        );

        foreach (EmployeeProfile::where('organization_id', $organization->id)->get() as $profile) {
            EmployeeScheduleAssignment::updateOrCreate(
                ['employee_profile_id' => $profile->id, 'work_schedule_id' => $schedule->id, 'valid_from' => now()->startOfYear()->toDateString()],
                ['valid_until' => null],
            );

            EmployeeCalendarAssignment::updateOrCreate(
                ['employee_profile_id' => $profile->id, 'holiday_calendar_id' => $calendar->id, 'valid_from' => now()->startOfYear()->toDateString()],
                ['valid_until' => null],
            );
        }

        $vacations = LeaveType::updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'VACATIONS'],
            [
                'name' => 'Vacaciones',
                'unit' => 'DAYS',
                'consumes_balance' => true,
                'balance_code' => 'VACATIONS',
                'requires_approval' => true,
                'attachment_requirement' => 'none',
                'is_medical' => false,
                'notice_value' => 30,
                'notice_unit' => 'days',
                'min_units' => 1,
                'max_units' => 15,
                'allow_retroactive' => false,
                'visible_to_employees' => true,
                'is_active' => true,
                'position' => 1,
            ],
        );

        $types = [
            [
                'code' => 'MEDICAL',
                'name' => 'Permiso medico',
                'unit' => 'MINUTES',
                'consumes_balance' => false,
                'attachment_requirement' => 'optional',
                'is_medical' => true,
                'notice_value' => 0,
                'min_units' => 30,
                'max_units' => 480,
                'position' => 2,
            ],
            [
                'code' => 'PERSONAL',
                'name' => 'Asuntos personales',
                'unit' => 'DAYS',
                'consumes_balance' => false,
                'attachment_requirement' => 'none',
                'is_medical' => false,
                'notice_value' => 0,
                'min_units' => 1,
                'max_units' => 5,
                'position' => 3,
            ],
            [
                'code' => 'TRAINING',
                'name' => 'Formacion',
                'unit' => 'DAYS',
                'consumes_balance' => false,
                'attachment_requirement' => 'optional',
                'is_medical' => false,
                'notice_value' => 0,
                'min_units' => 1,
                'max_units' => 10,
                'position' => 4,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $type['code']],
                array_merge([
                    'requires_approval' => true,
                    'balance_code' => null,
                    'notice_unit' => 'days',
                    'allow_retroactive' => false,
                    'visible_to_employees' => true,
                    'is_active' => true,
                ], $type),
            );
        }

        foreach (EmployeeProfile::where('organization_id', $organization->id)->get() as $profile) {
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

            LeaveBalanceMovement::firstOrCreate(
                ['idempotency_key' => 'seed-allocation-'.$allowance->id],
                [
                    'organization_id' => $organization->id,
                    'leave_allowance_id' => $allowance->id,
                    'movement_type' => 'ALLOCATION',
                    'amount' => 15,
                    'reason' => 'Asignacion anual inicial',
                    'created_by' => $admin->id,
                    'created_at' => now(),
                ],
            );
        }

        foreach ([
            'REQUEST_CREATED' => ['admin', 'Nueva solicitud pendiente'],
            'REQUEST_APPROVED' => ['user', 'Solicitud aprobada'],
            'REQUEST_REJECTED' => ['user', 'Solicitud rechazada'],
            'CANCELLATION_REQUESTED' => ['admin', 'Cancelacion solicitada'],
            'CANCELLATION_ACCEPTED' => ['user', 'Cancelacion aceptada'],
            'CANCELLATION_REJECTED' => ['user', 'Cancelacion rechazada'],
        ] as $event => [$recipient, $subject]) {
            NotificationRule::updateOrCreate(
                ['organization_id' => $organization->id, 'event' => $event, 'recipient_type' => $recipient],
                [
                    'is_active' => true,
                    'subject_template' => '[N-Woffu Prime] '.$subject,
                    'body_template' => 'Revisa el detalle dentro de N-Woffu Prime. Por seguridad, los documentos medicos no se envian por correo.',
                ],
            );
        }
    }
}
