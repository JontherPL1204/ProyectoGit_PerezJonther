<?php

namespace App\Http\Controllers;

use App\Models\NotificationOutbox;
use App\Services\NotificationService;
use App\Services\OrganizationDataCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly OrganizationDataCache $dataCache,
    ) {}

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

        $notifications = DB::table('notification_outbox')
            ->leftJoin('leave_requests', 'leave_requests.id', '=', 'notification_outbox.leave_request_id')
            ->leftJoin('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
            ->leftJoin('users', 'users.id', '=', 'employee_profiles.user_id')
            ->leftJoin('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('notification_outbox.organization_id', $request->user()->organization_id)
            ->when($currentStatus !== 'all', fn ($query) => $query->where('notification_outbox.status', $currentStatus))
            ->when($currentEvent !== '', fn ($query) => $query->where('notification_outbox.event', $currentEvent))
            ->select([
                'notification_outbox.id',
                'notification_outbox.leave_request_id',
                'notification_outbox.event',
                'notification_outbox.recipient_email',
                'notification_outbox.subject',
                'notification_outbox.status',
                'notification_outbox.attempts',
                'notification_outbox.sent_at',
                'notification_outbox.last_error',
                'notification_outbox.created_at',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
            ])
            ->orderByDesc('notification_outbox.created_at')
            ->simplePaginate(15)
            ->withQueryString();

        $notifications->getCollection()->transform(function ($row) {
            $row->created_at = CarbonImmutable::parse($row->created_at);
            $row->sent_at = $row->sent_at ? CarbonImmutable::parse($row->sent_at) : null;
            $row->status_label = $this->statusLabel($row->status);
            $row->event_label = $this->eventLabel($row->event);

            return $row;
        });

        $events = $this->dataCache->notificationEvents($request->user()->organization_id);

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
        $this->dataCache->forgetRequestData($request->user()->organization_id);

        $notification->refresh();

        if ($notification->status === 'sent') {
            return back()->with('status', 'Correo reenviado correctamente.');
        }

        return back()->with('status', 'No se pudo enviar ahora. Quedo en cola para reintento.');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            default => 'Pendiente',
        };
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'REQUEST_CREATED' => 'Nueva solicitud',
            'REQUEST_APPROVED' => 'Solicitud aprobada',
            'REQUEST_REJECTED' => 'Solicitud rechazada',
            'CANCELLATION_REQUESTED' => 'Cancelacion solicitada',
            'CANCELLATION_ACCEPTED' => 'Cancelacion aceptada',
            'CANCELLATION_REJECTED' => 'Cancelacion rechazada',
            default => $event,
        };
    }
}
