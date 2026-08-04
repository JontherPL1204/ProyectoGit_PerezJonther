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
        $this->assertStringStartsWith('%PDF-1.4', $response->streamedContent());
    }
}
