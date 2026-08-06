<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_demo_attachment_downloads_generated_pdf(): void
    {
        $this->seed();

        $admin = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
        $employee = User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
        $leaveType = LeaveType::where('code', 'PERSONAL')->firstOrFail();
        $leaveRequest = LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $leaveType->id,
            'unit' => 'DAYS',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_PENDING,
            'version' => 1,
        ]);

        $attachment = RequestAttachment::create([
            'organization_id' => $leaveRequest->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'uploaded_by' => $leaveRequest->employeeProfile->user_id,
            'original_name' => 'justificante-demo-test.pdf',
            'stored_name' => 'justificante-demo-test.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'demo/justificantes/missing-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'is_medical' => false,
            'checksum' => hash('sha256', 'missing-test'),
        ]);

        Storage::disk('local')->delete($attachment->storage_path);

        $response = $this->actingAs($admin)->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename="justificante-demo-test.pdf"');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_preview_streams_supported_private_attachment_inline(): void
    {
        $this->seed();

        $admin = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
        $employee = User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
        $leaveType = LeaveType::where('code', 'PERSONAL')->firstOrFail();
        $leaveRequest = LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $leaveType->id,
            'unit' => 'DAYS',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_PENDING,
            'version' => 1,
        ]);

        $attachment = RequestAttachment::create([
            'organization_id' => $leaveRequest->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'uploaded_by' => $leaveRequest->employeeProfile->user_id,
            'original_name' => 'justificante-demo-preview.pdf',
            'stored_name' => 'justificante-demo-preview.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'demo/justificantes/missing-preview.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'is_medical' => false,
            'checksum' => hash('sha256', 'missing-preview'),
        ]);

        Storage::disk('local')->delete($attachment->storage_path);

        $response = $this->actingAs($admin)->get(route('attachments.preview', $attachment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename="justificante-demo-preview.pdf"');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_existing_attachment_can_be_previewed_and_downloaded(): void
    {
        $this->seed();

        $admin = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
        $employee = User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
        $leaveType = LeaveType::where('code', 'PERSONAL')->firstOrFail();
        $leaveRequest = LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $leaveType->id,
            'unit' => 'DAYS',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-12',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_PENDING,
            'version' => 1,
        ]);
        $path = 'request-attachments/test/existing-preview.pdf';
        $content = "%PDF-1.4\n% existing test\n%%EOF\n";

        Storage::disk('local')->put($path, $content);

        $attachment = RequestAttachment::create([
            'organization_id' => $leaveRequest->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'uploaded_by' => $leaveRequest->employeeProfile->user_id,
            'original_name' => 'justificante-existente.pdf',
            'stored_name' => 'justificante-existente.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'is_medical' => false,
            'checksum' => hash('sha256', $content),
        ]);

        $preview = $this->actingAs($admin)->get(route('attachments.preview', $attachment));
        $preview->assertOk();
        $preview->assertHeader('content-disposition', 'inline; filename="justificante-existente.pdf"');
        $this->assertSame($content, $preview->getContent());

        $download = $this->actingAs($admin)->get(route('attachments.download', $attachment));
        $download->assertOk();
        $download->assertHeader('content-disposition', 'attachment; filename="justificante-existente.pdf"');
        $this->assertSame($content, $download->getContent());
    }

    public function test_database_content_keeps_preview_and_download_available_when_local_file_is_missing(): void
    {
        $this->seed();

        $admin = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
        $employee = User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
        $leaveType = LeaveType::where('code', 'PERSONAL')->firstOrFail();
        $leaveRequest = LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $leaveType->id,
            'unit' => 'DAYS',
            'start_date' => '2026-08-13',
            'end_date' => '2026-08-13',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_PENDING,
            'version' => 1,
        ]);
        $path = 'request-attachments/test/missing-local-db-backed.pdf';
        $content = "%PDF-1.4\n% database backed test\n%%EOF\n";

        Storage::disk('local')->delete($path);

        $attachment = RequestAttachment::create([
            'organization_id' => $leaveRequest->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'uploaded_by' => $leaveRequest->employeeProfile->user_id,
            'original_name' => 'justificante-db.pdf',
            'stored_name' => 'justificante-db.pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'is_medical' => false,
            'checksum' => hash('sha256', $content),
            'file_content' => $content,
        ]);

        $preview = $this->actingAs($admin)->get(route('attachments.preview', $attachment));
        $preview->assertOk();
        $preview->assertHeader('content-disposition', 'inline; filename="justificante-db.pdf"');
        $this->assertSame($content, $preview->getContent());

        $download = $this->actingAs($admin)->get(route('attachments.download', $attachment));
        $download->assertOk();
        $download->assertHeader('content-disposition', 'attachment; filename="justificante-db.pdf"');
        $this->assertSame($content, $download->getContent());
    }
}
