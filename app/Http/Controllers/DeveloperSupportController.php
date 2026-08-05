<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\NotificationOutbox;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeveloperSupportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->canAccessDeveloperSupport(), 403);

        $organizationId = $user->organization_id;

        $requestCounts = LeaveRequest::where('organization_id', $organizationId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $notificationCounts = NotificationOutbox::where('organization_id', $organizationId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $roleCounts = User::where('organization_id', $organizationId)
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        $recentFailures = NotificationOutbox::where('organization_id', $organizationId)
            ->where('status', 'failed')
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'event', 'recipient_email', 'last_error', 'updated_at']);

        $system = [
            'Entorno' => app()->environment(),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Base de datos' => config('database.default'),
            'Cache' => config('cache.default'),
            'Sesion' => config('session.driver'),
            'Correo' => config('mail.default'),
            'Cola' => config('queue.default'),
            'Storage' => config('filesystems.default'),
            'URL' => config('app.url'),
        ];

        $metrics = [
            'Pendientes' => (int) ($requestCounts[LeaveRequest::STATUS_PENDING] ?? 0),
            'Aprobadas' => (int) ($requestCounts[LeaveRequest::STATUS_APPROVED] ?? 0),
            'Correos fallidos' => (int) ($notificationCounts['failed'] ?? 0),
            'Adjuntos privados' => RequestAttachment::where('organization_id', $organizationId)->count(),
        ];

        return view('developer.support', compact(
            'metrics',
            'notificationCounts',
            'recentFailures',
            'roleCounts',
            'system',
        ));
    }
}
