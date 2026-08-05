<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\OrganizationDataCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RequestHistoryController extends Controller
{
    public function __construct(private readonly OrganizationDataCache $dataCache) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profileId = $this->currentEmployeeProfileId($request);
        $statusOptions = [
            LeaveRequest::STATUS_PENDING => 'Pendiente',
            LeaveRequest::STATUS_APPROVED => 'Aprobada',
            LeaveRequest::STATUS_REJECTED => 'Rechazada',
            LeaveRequest::STATUS_CANCELLED => 'Cancelada',
            LeaveRequest::STATUS_PENDING_CANCELLATION => 'Cancelacion pendiente',
        ];

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => array_key_exists((string) $request->query('estado'), $statusOptions)
                ? (string) $request->query('estado')
                : null,
            'employee_profile_id' => $user->isAdmin() ? ($request->integer('empleado') ?: null) : $profileId,
            'leave_type_id' => $request->integer('tipo') ?: null,
            'date_from' => $this->validDateFilter($request->query('desde')),
            'date_to' => $this->validDateFilter($request->query('hasta')),
        ];

        $requests = DB::table('leave_requests')
            ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
            ->join('users', 'users.id', '=', 'employee_profiles.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.organization_id', $user->organization_id)
            ->when(! $user->isAdmin(), fn ($query) => $query->where('leave_requests.employee_profile_id', $profileId))
            ->when($user->isAdmin() && $filters['employee_profile_id'], fn ($query, $employeeProfileId) => $query->where('leave_requests.employee_profile_id', $employeeProfileId))
            ->when($filters['leave_type_id'], fn ($query, $leaveTypeId) => $query->where('leave_requests.leave_type_id', $leaveTypeId))
            ->when($filters['status'], fn ($query, $status) => $query->where('leave_requests.status', $status))
            ->when($filters['date_from'], fn ($query, $dateFrom) => $query->whereDate('leave_requests.end_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn ($query, $dateTo) => $query->whereDate('leave_requests.start_date', '<=', $dateTo))
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = '%'.$filters['q'].'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('leave_requests.user_comment', 'like', $search)
                        ->orWhere('leave_requests.admin_comment', 'like', $search)
                        ->orWhere('leave_types.name', 'like', $search)
                        ->orWhere('users.name', 'like', $search);
                });
            })
            ->select([
                'leave_requests.id',
                'leave_requests.employee_profile_id',
                'leave_requests.status',
                'leave_requests.unit',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.requested_units',
                'leave_requests.user_comment',
                'leave_requests.admin_comment',
                'leave_requests.created_at',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
            ])
            ->orderByDesc('leave_requests.created_at')
            ->simplePaginate(15)
            ->withQueryString();

        $requests->getCollection()->transform(function ($row) {
            $row->start_date = CarbonImmutable::parse($row->start_date);
            $row->end_date = CarbonImmutable::parse($row->end_date);
            $row->status_label = $this->statusLabel($row->status);

            return $row;
        });

        $employees = $user->isAdmin()
            ? $this->dataCache->employees($user->organization_id)
            : collect();

        $leaveTypes = $this->dataCache->leaveTypes($user->organization_id);

        return view('leave_requests.history', compact(
            'employees',
            'filters',
            'leaveTypes',
            'requests',
            'statusOptions',
        ));
    }

    private function validDateFilter(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return null;
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return $value;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            LeaveRequest::STATUS_PENDING => 'Pendiente',
            LeaveRequest::STATUS_APPROVED => 'Aprobada',
            LeaveRequest::STATUS_REJECTED => 'Rechazada',
            LeaveRequest::STATUS_CANCELLED => 'Cancelada',
            LeaveRequest::STATUS_PENDING_CANCELLATION => 'Cancelacion pendiente',
            default => $status,
        };
    }
}
