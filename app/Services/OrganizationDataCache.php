<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NotificationOutbox;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

class OrganizationDataCache
{
    private const STATIC_TTL_SECONDS = 300;

    private const VOLATILE_TTL_SECONDS = 20;

    public function settings(int $organizationId): Fluent
    {
        return new Fluent(Cache::remember(
            $this->key($organizationId, 'settings'),
            self::STATIC_TTL_SECONDS,
            fn () => CompanySetting::where('organization_id', $organizationId)->firstOrFail()->attributesToArray(),
        ));
    }

    /**
     * @return Collection<int,Fluent>
     */
    public function leaveTypes(int $organizationId, bool $withDepartment = false): Collection
    {
        $rows = Cache::remember(
            $this->key($organizationId, $withDepartment ? 'leave-types-with-department' : 'leave-types'),
            self::STATIC_TTL_SECONDS,
            fn () => LeaveType::where('organization_id', $organizationId)
                ->when($withDepartment, fn ($query) => $query->with('department'))
                ->orderBy('position')
                ->get()
                ->map(fn (LeaveType $type): array => array_merge($type->attributesToArray(), [
                    'department' => $withDepartment && $type->department
                        ? $type->department->only(['id', 'name'])
                        : null,
                ]))
                ->all(),
        );

        return $this->toFluentCollection($rows);
    }

    /**
     * @return Collection<int,Fluent>
     */
    public function departments(int $organizationId): Collection
    {
        $rows = Cache::remember(
            $this->key($organizationId, 'departments'),
            self::STATIC_TTL_SECONDS,
            fn () => Department::where('organization_id', $organizationId)
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => $department->only(['id', 'name']))
                ->all(),
        );

        return $this->toFluentCollection($rows);
    }

    /**
     * @return Collection<int,Fluent>
     */
    public function employees(int $organizationId): Collection
    {
        $rows = Cache::remember(
            $this->key($organizationId, 'employees'),
            self::STATIC_TTL_SECONDS,
            fn () => EmployeeProfile::with('user')
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query
                    ->where('status', User::STATUS_ACTIVE)
                    ->whereNull('deactivated_at'))
                ->get()
                ->sortBy(fn (EmployeeProfile $profile) => $profile->user?->name ?? '')
                ->map(fn (EmployeeProfile $profile): array => [
                    'id' => $profile->id,
                    'department_id' => $profile->department_id,
                    'user' => $profile->user?->only(['id', 'name', 'email']),
                ])
                ->values()
                ->all(),
        );

        return $this->toFluentCollection($rows);
    }

    /**
     * @return Collection<int,Fluent>
     */
    public function users(int $organizationId): Collection
    {
        $rows = Cache::remember(
            $this->key($organizationId, 'users'),
            self::STATIC_TTL_SECONDS,
            fn () => User::with(['employeeProfile.department', 'employeeProfile.jobPositions'])
                ->where('organization_id', $organizationId)
                ->orderByRaw("CASE WHEN role = 'admin' THEN 0 WHEN role = 'developer' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'deactivated_at' => $user->deactivated_at,
                    'can_manage_company_rules' => (bool) $user->can_manage_company_rules,
                    'can_view_medical_attachments' => (bool) $user->can_view_medical_attachments,
                    'employeeProfile' => $user->employeeProfile ? [
                        'id' => $user->employeeProfile->id,
                        'is_active' => (bool) $user->employeeProfile->is_active,
                        'department' => $user->employeeProfile->department?->only(['id', 'name']),
                        'jobPositions' => $user->employeeProfile->jobPositions
                            ->sortBy('name')
                            ->map(fn (JobPosition $position): array => $position->only(['id', 'name']))
                            ->values()
                            ->all(),
                    ] : null,
                ])
                ->all(),
        );

        return $this->toFluentCollection($rows);
    }

    /**
     * @return Collection<int,Fluent>
     */
    public function jobPositions(int $organizationId): Collection
    {
        $rows = Cache::remember(
            $this->key($organizationId, 'job-positions'),
            self::STATIC_TTL_SECONDS,
            fn () => JobPosition::withCount([
                'employeeProfiles' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('user', fn ($userQuery) => $userQuery
                        ->where('status', User::STATUS_ACTIVE)
                        ->whereNull('deactivated_at')),
            ])
                ->where('organization_id', $organizationId)
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get()
                ->map(fn (JobPosition $position): array => array_merge($position->attributesToArray(), [
                    'employee_profiles_count' => $position->employee_profiles_count,
                ]))
                ->all(),
        );

        return $this->toFluentCollection($rows);
    }

    /**
     * @return Collection<int,string>
     */
    public function notificationEvents(int $organizationId): Collection
    {
        return collect(Cache::remember(
            $this->key($organizationId, 'notification-events'),
            self::STATIC_TTL_SECONDS,
            fn () => NotificationOutbox::where('organization_id', $organizationId)
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event')
                ->all(),
        ));
    }

    /**
     * @return Collection<int,Fluent>
     */
    public function notificationRules(int $organizationId): Collection
    {
        $rows = Cache::remember(
            $this->key($organizationId, 'notification-rules'),
            self::STATIC_TTL_SECONDS,
            fn () => NotificationRule::where('organization_id', $organizationId)
                ->orderBy('event')
                ->orderBy('recipient_type')
                ->get()
                ->map(fn (NotificationRule $rule): array => $rule->attributesToArray())
                ->all(),
        );

        return $this->toFluentCollection($rows);
    }

    /**
     * @return array<string,int>
     */
    public function requestStatusCounts(int $organizationId): array
    {
        return Cache::remember(
            $this->key($organizationId, 'request-status-counts'),
            self::VOLATILE_TTL_SECONDS,
            function () use ($organizationId): array {
                $counts = LeaveRequest::where('organization_id', $organizationId)
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status');

                return [
                    'pending' => (int) ($counts[LeaveRequest::STATUS_PENDING] ?? 0),
                    'approved' => (int) ($counts[LeaveRequest::STATUS_APPROVED] ?? 0),
                    'pending_cancellation' => (int) ($counts[LeaveRequest::STATUS_PENDING_CANCELLATION] ?? 0),
                    'rejected' => (int) ($counts[LeaveRequest::STATUS_REJECTED] ?? 0),
                    'cancelled' => (int) ($counts[LeaveRequest::STATUS_CANCELLED] ?? 0),
                ];
            },
        );
    }

    public function forgetOrganization(int $organizationId): void
    {
        foreach ([
            'settings',
            'leave-types',
            'leave-types-with-department',
            'departments',
            'employees',
            'users',
            'job-positions',
            'notification-events',
            'notification-rules',
            'request-status-counts',
        ] as $name) {
            Cache::forget($this->key($organizationId, $name));
        }
    }

    public function forgetRequestData(int $organizationId): void
    {
        Cache::forget($this->key($organizationId, 'request-status-counts'));
        Cache::forget($this->key($organizationId, 'notification-events'));
        Cache::forever(
            $this->key($organizationId, 'request-data-version'),
            $this->requestDataVersion($organizationId) + 1,
        );
    }

    public function requestDataVersion(int $organizationId): int
    {
        return (int) Cache::get($this->key($organizationId, 'request-data-version'), 1);
    }

    private function key(int $organizationId, string $name): string
    {
        return "organization:$organizationId:$name:v1";
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return Collection<int,Fluent>
     */
    private function toFluentCollection(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row) => $this->toFluent($row));
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function toFluent(array $row): Fluent
    {
        return new Fluent(collect($row)
            ->map(function ($value) {
                if (! is_array($value)) {
                    return $value;
                }

                if (array_is_list($value)) {
                    return collect($value)->map(fn ($item) => is_array($item) ? $this->toFluent($item) : $item);
                }

                return $this->toFluent($value);
            })
            ->all());
    }
}
