<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\RequestAttachment;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function download(Request $request, RequestAttachment $requestAttachment): StreamedResponse
    {
        $leaveRequest = $this->authorizeAttachmentAccess($request, $requestAttachment);

        $this->audit->requestEvent(
            $leaveRequest,
            'ATTACHMENT_VIEWED',
            $request->user(),
            $leaveRequest->status,
            $leaveRequest->status,
            'Descarga de adjunto',
            ['attachment_id' => $requestAttachment->id, 'is_medical' => $requestAttachment->is_medical],
            $request,
        );

        $disk = Storage::disk($requestAttachment->storage_disk);

        if ($disk->exists($requestAttachment->storage_path)) {
            return $disk->download($requestAttachment->storage_path, $requestAttachment->original_name);
        }

        if ($this->isDemoAttachment($requestAttachment)) {
            return response()->streamDownload(
                function () use ($requestAttachment, $leaveRequest): void {
                    echo $this->demoPdfContent($requestAttachment, $leaveRequest);
                },
                $requestAttachment->original_name,
                ['Content-Type' => 'application/pdf'],
            );
        }

        abort(Response::HTTP_NOT_FOUND, 'El adjunto ya no esta disponible en el servidor.');
    }

    public function preview(Request $request, RequestAttachment $requestAttachment): StreamedResponse
    {
        $leaveRequest = $this->authorizeAttachmentAccess($request, $requestAttachment);

        abort_unless($this->isPreviewable($requestAttachment), Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'Este archivo no tiene vista previa disponible.');

        $this->audit->requestEvent(
            $leaveRequest,
            'ATTACHMENT_PREVIEWED',
            $request->user(),
            $leaveRequest->status,
            $leaveRequest->status,
            'Vista previa de adjunto',
            ['attachment_id' => $requestAttachment->id, 'is_medical' => $requestAttachment->is_medical],
            $request,
        );

        $disk = Storage::disk($requestAttachment->storage_disk);
        $fileName = str_replace('"', '', Str::ascii($requestAttachment->original_name));

        if ($disk->exists($requestAttachment->storage_path)) {
            return response()->stream(function () use ($disk, $requestAttachment): void {
                $stream = $disk->readStream($requestAttachment->storage_path);

                if ($stream === false) {
                    return;
                }

                fpassthru($stream);
                fclose($stream);
            }, Response::HTTP_OK, [
                'Content-Type' => $requestAttachment->mime_type,
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Cache-Control' => 'private, max-age=0, no-store',
            ]);
        }

        if ($this->isDemoAttachment($requestAttachment)) {
            return response()->stream(function () use ($requestAttachment, $leaveRequest): void {
                echo $this->demoPdfContent($requestAttachment, $leaveRequest);
            }, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Cache-Control' => 'private, max-age=0, no-store',
            ]);
        }

        abort(Response::HTTP_NOT_FOUND, 'El adjunto ya no esta disponible en el servidor.');
    }

    public function markReviewed(Request $request, RequestAttachment $requestAttachment): RedirectResponse
    {
        $user = $request->user();
        $leaveRequest = $requestAttachment->leaveRequest()->firstOrFail();

        abort_unless($user?->isAdmin(), 403);
        abort_unless($requestAttachment->organization_id === $user->organization_id, 404);

        if ($requestAttachment->is_medical) {
            abort_unless($user->canViewMedicalAttachments(), 403);
        }

        $requestAttachment->update([
            'justification_status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
        ]);

        $this->audit->requestEvent(
            $leaveRequest,
            'ATTACHMENT_REVIEWED',
            $user,
            $leaveRequest->status,
            $leaveRequest->status,
            'Justificante revisado',
            ['attachment_id' => $requestAttachment->id],
            $request,
        );

        return back()->with('status', 'Justificante marcado como revisado.');
    }

    private function isDemoAttachment(RequestAttachment $requestAttachment): bool
    {
        return $requestAttachment->storage_disk === 'local'
            && Str::startsWith($requestAttachment->storage_path, 'demo/justificantes/');
    }

    private function isPreviewable(RequestAttachment $requestAttachment): bool
    {
        return in_array($requestAttachment->mime_type, [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ], true);
    }

    private function authorizeAttachmentAccess(Request $request, RequestAttachment $requestAttachment): LeaveRequest
    {
        $user = $request->user();
        $leaveRequest = $requestAttachment->leaveRequest()->with('employeeProfile.user', 'leaveType')->firstOrFail();

        abort_unless($requestAttachment->organization_id === $user->organization_id, 404);

        $isOwner = $leaveRequest->employee_profile_id === $user->employeeProfile?->id;
        $canAdminView = $user->canViewMedicalAttachments();

        if ($requestAttachment->is_medical) {
            abort_unless($isOwner || $canAdminView, 403);
        } else {
            abort_unless($isOwner || $user->isAdmin(), 403);
        }

        return $leaveRequest;
    }

    private function demoPdfContent(RequestAttachment $requestAttachment, LeaveRequest $leaveRequest): string
    {
        $employee = $leaveRequest->employeeProfile?->user?->name ?? 'Empleado demo';
        $leaveType = $leaveRequest->leaveType?->name ?? 'Justificante';

        return $this->pdf([
            'N-Woffu Prime',
            'Justificante demo',
            'Solicitud #'.$leaveRequest->id,
            'Empleado: '.$employee,
            'Tipo: '.$leaveType,
            'Periodo: '.$leaveRequest->start_date->format('d/m/Y').' - '.$leaveRequest->end_date->format('d/m/Y'),
            'Estado: '.$leaveRequest->statusLabel(),
            'Archivo: '.$requestAttachment->original_name,
            'Este PDF se genera automaticamente porque Vercel no conserva archivos demo locales.',
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function pdf(array $lines): string
    {
        $text = collect($lines)
            ->map(fn (string $line, int $index): string => $this->pdfTextLine($line, $index === 0))
            ->implode("\n");

        $stream = "BT\n72 720 Td\n".$text."\nET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function pdfTextLine(string $line, bool $title = false): string
    {
        $font = $title ? '/F2 18 Tf' : '/F1 11 Tf';
        $offset = $title ? '0 -34 Td' : '0 -20 Td';

        return $font."\n(".$this->escapePdfText($line).") Tj\n".$offset;
    }

    private function escapePdfText(string $text): string
    {
        $text = Str::ascii($text);
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
