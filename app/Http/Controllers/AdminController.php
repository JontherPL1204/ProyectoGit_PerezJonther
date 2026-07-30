<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\ApprovalService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $requests = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])
            ->orderBy('start_date')
            ->latest()
            ->get();

        $stats = [
            'pending' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count(),
            'approved' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->count(),
            'pending_cancellation' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_PENDING_CANCELLATION)
                ->count(),
        ];

        return view('admin.dashboard', compact('requests', 'stats'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['nullable', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->approve($leaveRequest, $request->user(), $data['admin_comment'] ?? null, $request);
            $this->notifications->requestResolved($updated, 'REQUEST_APPROVED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Solicitud aprobada.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['required', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->reject($leaveRequest, $request->user(), $data['admin_comment'], $request);
            $this->notifications->requestResolved($updated, 'REQUEST_REJECTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Solicitud rechazada.');
    }

    public function acceptCancellation(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['nullable', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->resolveCancellation($leaveRequest, $request->user(), true, $data['admin_comment'] ?? null, $request);
            $this->notifications->requestResolved($updated, 'CANCELLATION_ACCEPTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancelacion aceptada.');
    }

    public function rejectCancellation(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['required', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->resolveCancellation($leaveRequest, $request->user(), false, $data['admin_comment'], $request);
            $this->notifications->requestResolved($updated, 'CANCELLATION_REJECTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancelacion rechazada.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function authorizeAdminForRequest(Request $request, LeaveRequest $leaveRequest): void
    {
        $this->authorizeAdmin($request);
        abort_unless($leaveRequest->organization_id === $request->user()->organization_id, 404);
    }
}
