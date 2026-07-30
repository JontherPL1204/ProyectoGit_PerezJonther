<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequestHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->employeeProfile()->firstOrFail();
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
            'employee_profile_id' => $user->isAdmin() ? ($request->integer('empleado') ?: null) : $profile->id,
            'leave_type_id' => $request->integer('tipo') ?: null,
            'date_from' => $this->validDateFilter($request->query('desde')),
            'date_to' => $this->validDateFilter($request->query('hasta')),
        ];

        $requests = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $user->organization_id)
            ->when(! $user->isAdmin(), fn ($query) => $query->where('employee_profile_id', $profile->id))
            ->when($user->isAdmin() && $filters['employee_profile_id'], fn ($query, $employeeProfileId) => $query->where('employee_profile_id', $employeeProfileId))
            ->when($filters['leave_type_id'], fn ($query, $leaveTypeId) => $query->where('leave_type_id', $leaveTypeId))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $dateFrom) => $query->whereDate('end_date', '>=', $dateFrom))
            ->when($filters['date_to'], fn ($query, $dateTo) => $query->whereDate('start_date', '<=', $dateTo))
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = '%'.$filters['q'].'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('user_comment', 'like', $search)
                        ->orWhere('admin_comment', 'like', $search)
                        ->orWhereHas('leaveType', fn ($typeQuery) => $typeQuery->where('name', 'like', $search))
                        ->orWhereHas('employeeProfile.user', fn ($userQuery) => $userQuery->where('name', 'like', $search));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $employees = $user->isAdmin()
            ? EmployeeProfile::with('user')
                ->where('organization_id', $user->organization_id)
                ->get()
                ->sortBy(fn (EmployeeProfile $employee) => $employee->user?->name ?? '')
            : collect();

        $leaveTypes = LeaveType::where('organization_id', $user->organization_id)
            ->orderBy('position')
            ->get();

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
}
