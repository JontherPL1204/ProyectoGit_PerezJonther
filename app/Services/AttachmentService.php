<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AttachmentService
{
    /**
     * @param  array<int,UploadedFile>  $files
     */
    public function storeMany(LeaveRequest $leaveRequest, array $files, User $actor): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $storedName = (string) Str::uuid().($extension ? '.'.$extension : '');
            $path = 'request-attachments/'.$leaveRequest->organization_id.'/'.$leaveRequest->id.'/'.$storedName;
            $content = file_get_contents($file->getRealPath());

            if (! is_string($content)) {
                throw new RuntimeException('No se pudo leer el archivo adjunto.');
            }

            try {
                Storage::disk('local')->put($path, $content);
            } catch (Throwable $exception) {
                if (! Schema::hasColumn('request_attachments', 'file_content')) {
                    throw $exception;
                }
            }

            $data = [
                'organization_id' => $leaveRequest->organization_id,
                'leave_request_id' => $leaveRequest->id,
                'uploaded_by' => $actor->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'storage_disk' => 'local',
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'is_medical' => $leaveRequest->leaveType->is_medical,
                'justification_status' => 'received',
                'checksum' => hash('sha256', $content),
            ];

            if (Schema::hasColumn('request_attachments', 'file_content')) {
                $data['file_content'] = $content;
            }

            RequestAttachment::create($data);
        }
    }
}
