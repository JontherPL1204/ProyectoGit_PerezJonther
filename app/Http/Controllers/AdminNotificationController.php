<?php

namespace App\Http\Controllers;

use App\Models\NotificationOutbox;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $statusOptions = [
            'all' => 'Todos',
            'pending' => 'Pendientes',
            'sent' => 'Enviados',
            'failed' => 'Fallidos',
        ];
        $currentStatus = array_key_exists((string) $request->query('estado'), $statusOptions)
            ? (string) $request->query('estado')
            : 'all';
        $currentEvent = trim((string) $request->query('evento', ''));

        $notifications = NotificationOutbox::with(['leaveRequest.employeeProfile.user', 'leaveRequest.leaveType'])
            ->where('organization_id', $request->user()->organization_id)
            ->when($currentStatus !== 'all', fn ($query) => $query->where('status', $currentStatus))
            ->when($currentEvent !== '', fn ($query) => $query->where('event', $currentEvent))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $events = NotificationOutbox::where('organization_id', $request->user()->organization_id)
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return view('admin.notifications', compact(
            'currentEvent',
            'currentStatus',
            'events',
            'notifications',
            'statusOptions',
        ));
    }

    public function resend(Request $request, NotificationOutbox $notification): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($notification->organization_id === $request->user()->organization_id, 404);

        $notification->update([
            'status' => 'pending',
            'available_at' => now(),
            'last_error' => null,
        ]);

        $this->notifications->sendNow($notification->fresh());

        $notification->refresh();

        if ($notification->status === 'sent') {
            return back()->with('status', 'Correo reenviado correctamente.');
        }

        return back()->with('status', 'No se pudo enviar ahora. Quedo en cola para reintento.');
    }
}
