<?php

namespace App\Http\Controllers;

use App\Models\RequestAttachment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function download(Request $request, RequestAttachment $requestAttachment): StreamedResponse
    {
        $user = $request->user();
        $leaveRequest = $requestAttachment->leaveRequest()->with('employeeProfile.user')->firstOrFail();

        abort_unless($requestAttachment->organization_id === $user->organization_id, 404);

        $isOwner = $leaveRequest->employee_profile_id === $user->employeeProfile?->id;
        $canAdminView = $user->canViewMedicalAttachments();

        if ($requestAttachment->is_medical) {
            abort_unless($isOwner || $canAdminView, 403);
        } else {
            abort_unless($isOwner || $user->isAdmin(), 403);
        }

        $this->audit->requestEvent(
            $leaveRequest,
            'ATTACHMENT_VIEWED',
            $user,
            $leaveRequest->status,
            $leaveRequest->status,
            'Descarga de adjunto',
            ['attachment_id' => $requestAttachment->id, 'is_medical' => $requestAttachment->is_medical],
            $request,
        );

        return Storage::disk($requestAttachment->storage_disk)
            ->download($requestAttachment->storage_path, $requestAttachment->original_name);
    }
}
