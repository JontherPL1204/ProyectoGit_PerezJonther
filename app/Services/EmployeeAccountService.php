<?php

namespace App\Services;

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
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeAccountService
{
    /**
     * @param  array{name:string,email:string,password:string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $organization = $this->defaultOrganization();
            $isFirstUser = ! User::where('organization_id', $organization->id)->exists();

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => $isFirstUser ? 'admin' : 'user',
                'status' => 'active',
                'can_manage_company_rules' => $isFirstUser,
                'can_view_medical_attachments' => $isFirstUser,
            ]);

            $this->provisionEmployeeProfile($user, $organization);

            return $user->fresh(['employeeProfile']);
        });
    }

    private function defaultOrganization(): Organization
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

        CompanySetting::firstOrCreate(
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

        return $organization;
    }

    private function provisionEmployeeProfile(User $user, Organization $organization): EmployeeProfile
    {
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

        $profile = EmployeeProfile::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'department_id' => $department->id,
            'location_id' => $location->id,
            'employee_code' => 'USR-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'hired_at' => now()->toDateString(),
            'is_active' => true,
        ]);

        $schedule = $this->defaultSchedule($organization);
        EmployeeScheduleAssignment::create([
            'employee_profile_id' => $profile->id,
            'work_schedule_id' => $schedule->id,
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_until' => null,
        ]);

        $calendar = HolidayCalendar::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Calendario empresa'],
            ['is_active' => true],
        );
        EmployeeCalendarAssignment::create([
            'employee_profile_id' => $profile->id,
            'holiday_calendar_id' => $calendar->id,
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_until' => null,
        ]);

        $this->assignVacationBalance($profile, $organization, $user);

        return $profile;
    }

    private function defaultSchedule(Organization $organization): WorkSchedule
    {
        $schedule = WorkSchedule::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Lunes a viernes'],
            ['is_active' => true],
        );

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            WorkScheduleDay::firstOrCreate(
                ['work_schedule_id' => $schedule->id, 'weekday' => $weekday],
                [
                    'is_working_day' => $weekday <= 5,
                    'work_minutes' => $weekday <= 5 ? 480 : 0,
                ],
            );
        }

        return $schedule;
    }

    private function assignVacationBalance(EmployeeProfile $profile, Organization $organization, User $createdBy): void
    {
        $settings = CompanySetting::where('organization_id', $organization->id)->first();

        $vacations = LeaveType::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'VACATIONS'],
            [
                'name' => 'Vacaciones',
                'unit' => 'DAYS',
                'consumes_balance' => true,
                'balance_code' => 'VACATIONS',
                'requires_approval' => true,
                'attachment_requirement' => 'none',
                'is_medical' => false,
                'notice_value' => $settings?->vacation_notice_days ?? 30,
                'notice_unit' => 'days',
                'min_units' => 1,
                'max_units' => $settings?->annual_vacation_days ?? 15,
                'allow_retroactive' => false,
                'visible_to_employees' => true,
                'is_active' => true,
                'position' => 1,
            ],
        );

        $allowance = LeaveAllowance::create([
            'organization_id' => $organization->id,
            'employee_profile_id' => $profile->id,
            'leave_type_id' => $vacations->id,
            'balance_code' => 'VACATIONS',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'assigned_units' => $settings?->annual_vacation_days ?? 15,
        ]);

        LeaveBalanceMovement::create([
            'organization_id' => $organization->id,
            'leave_allowance_id' => $allowance->id,
            'movement_type' => 'ALLOCATION',
            'amount' => $settings?->annual_vacation_days ?? 15,
            'idempotency_key' => 'registration-allocation-'.$allowance->id,
            'reason' => 'Asignacion anual al crear cuenta',
            'created_by' => $createdBy->id,
            'created_at' => now(),
        ]);
    }
}
