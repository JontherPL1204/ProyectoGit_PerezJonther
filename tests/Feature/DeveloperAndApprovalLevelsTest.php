<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeveloperAndApprovalLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_role_has_support_access_without_admin_permissions(): void
    {
        $this->seed();

        $employee = $this->employeeUser();
        $developer = $this->createTeamUser('Soporte Dev', 'dev@n-woffu-prime.local', User::ROLE_DEVELOPER);
        $medical = LeaveType::where('code', 'MEDICAL')->firstOrFail();

        $leaveRequest = LeaveRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_profile_id' => $employee->employeeProfile->id,
            'leave_type_id' => $medical->id,
            'unit' => 'DAYS',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'requested_units' => 1,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $attachment = RequestAttachment::create([
            'organization_id' => $employee->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'uploaded_by' => $employee->id,
            'original_name' => 'justificante-dev-test.pdf',
            'stored_name' => 'justificante-dev-test.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'demo/justificantes/dev-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'is_medical' => true,
            'checksum' => hash('sha256', 'dev-test'),
        ]);

        $this->actingAs($developer)
            ->get(route('developer.support'))
            ->assertOk()
            ->assertSee('Diagnostico tecnico')
            ->assertSee('Sin acceso');

        $this->actingAs($developer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($developer)->get(route('admin.rules.edit'))->assertForbidden();
        $this->actingAs($developer)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($developer)->get(route('admin.management.index'))->assertForbidden();
        $this->actingAs($developer)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_admin_can_assign_and_invite_developer_role(): void
    {
        $this->seed();

        $admin = $this->adminUser();
        $employee = $this->employeeUser();

        $this->actingAs($admin)
            ->post(route('admin.users.promote'), [
                'email' => $employee->email,
                'role' => User::ROLE_DEVELOPER,
                'can_manage_company_rules' => '1',
                'can_view_medical_attachments' => '1',
            ])
            ->assertSessionHas('status');

        $employee->refresh();

        $this->assertSame(User::ROLE_DEVELOPER, $employee->role);
        $this->assertFalse($employee->can_manage_company_rules);
        $this->assertFalse($employee->can_view_medical_attachments);

        $this->actingAs($employee)->get(route('developer.support'))->assertOk();
        $this->actingAs($employee)->get(route('admin.dashboard'))->assertForbidden();

        Mail::fake();

        $response = $this->actingAs($admin)->post(route('admin.management.invitations.store'), [
            'email' => 'nuevo.dev@example.com',
            'initial_role' => User::ROLE_DEVELOPER,
        ]);

        $response->assertSessionHas('invite_link');
        $inviteLink = $response->getSession()->get('invite_link');
        $token = basename(parse_url($inviteLink, PHP_URL_PATH));

        $this->post(route('invitations.accept', $token), [
            'name' => 'Nuevo Dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'America/Guayaquil',
        ])->assertRedirect(route('dashboard'));

        $invited = User::where('email', 'nuevo.dev@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_DEVELOPER, $invited->role);
        $this->assertFalse($invited->can_manage_company_rules);
        $this->assertFalse($invited->can_view_medical_attachments);
    }

    public function test_two_level_approval_stays_pending_until_manager_finishes_it(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        Mail::fake();
        $this->seed();

        $employee = $this->employeeUser();
        $manager = $this->adminUser();
        $limitedAdmin = $this->createTeamUser('Responsable Nivel 1', 'nivel1@n-woffu-prime.local', User::ROLE_ADMIN);
        $personal = LeaveType::where('code', 'PERSONAL')->firstOrFail();
        $personal->update([
            'approval_level_count' => 2,
            'requires_approval' => true,
            'auto_approve' => false,
        ]);

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $personal->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'user_comment' => 'Tramite familiar.',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('leave_type_id', $personal->id)->firstOrFail();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $leaveRequest->status);
        $this->assertSame(2, ApprovalStep::where('leave_request_id', $leaveRequest->id)->count());

        $this->actingAs($limitedAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Revision 1 de 2');

        $this->actingAs($limitedAdmin)->post(route('admin.requests.approve', $leaveRequest), [
            'admin_comment' => 'Cobertura revisada.',
        ])->assertSessionHas('status', 'Nivel aprobado. La solicitud sigue pendiente del siguiente nivel.');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $leaveRequest->status);
        $this->assertDatabaseHas('approval_steps', [
            'leave_request_id' => $leaveRequest->id,
            'level' => 1,
            'status' => ApprovalStep::STATUS_APPROVED,
            'decided_by' => $limitedAdmin->id,
        ]);
        $this->assertDatabaseMissing('notification_outbox', [
            'leave_request_id' => $leaveRequest->id,
            'event' => 'REQUEST_APPROVED',
        ]);

        $this->actingAs($limitedAdmin)->post(route('admin.requests.approve', $leaveRequest), [
            'admin_comment' => 'Intento nivel 2.',
        ])->assertSessionHasErrors('request');

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Revision 2 de 2');

        $this->actingAs($manager)->post(route('admin.requests.approve', $leaveRequest), [
            'admin_comment' => 'Aprobacion final.',
        ])->assertSessionHas('status', 'Solicitud aprobada.');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertDatabaseHas('approval_steps', [
            'leave_request_id' => $leaveRequest->id,
            'level' => 2,
            'status' => ApprovalStep::STATUS_APPROVED,
            'decided_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('notification_outbox', [
            'leave_request_id' => $leaveRequest->id,
            'event' => 'REQUEST_APPROVED',
            'recipient_email' => $employee->email,
            'status' => 'sent',
        ]);

        $this->actingAs($employee)
            ->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()
            ->assertSee('Nivel 1 - Aprobado')
            ->assertSee('Nivel 2 - Aprobado');
    }

    public function test_reject_at_current_approval_level_rejects_request(): void
    {
        Carbon::setTestNow('2026-07-30 10:00:00');
        Mail::fake();
        $this->seed();

        $employee = $this->employeeUser();
        $limitedAdmin = $this->createTeamUser('Responsable Nivel 1', 'rechazo@n-woffu-prime.local', User::ROLE_ADMIN);
        $training = LeaveType::where('code', 'TRAINING')->firstOrFail();
        $training->update([
            'approval_level_count' => 2,
            'requires_approval' => true,
            'auto_approve' => false,
        ]);

        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $training->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('leave_type_id', $training->id)->firstOrFail();

        $this->actingAs($limitedAdmin)->post(route('admin.requests.reject', $leaveRequest), [
            'admin_comment' => 'No hay cobertura para esa fecha.',
        ])->assertSessionHas('status');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequest::STATUS_REJECTED, $leaveRequest->status);
        $this->assertDatabaseHas('approval_steps', [
            'leave_request_id' => $leaveRequest->id,
            'level' => 1,
            'status' => ApprovalStep::STATUS_REJECTED,
            'decided_by' => $limitedAdmin->id,
        ]);
        $this->assertDatabaseHas('request_events', [
            'leave_request_id' => $leaveRequest->id,
            'action' => 'APPROVAL_LEVEL_REJECTED',
        ]);
    }

    private function createTeamUser(string $name, string $email, string $role): User
    {
        $baseProfile = $this->employeeUser()->employeeProfile;

        $user = User::factory()->create([
            'organization_id' => $baseProfile->organization_id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => 'active',
            'timezone' => 'America/Guayaquil',
            'can_manage_company_rules' => false,
            'can_view_medical_attachments' => false,
        ]);

        $user->employeeProfile()->create([
            'organization_id' => $baseProfile->organization_id,
            'department_id' => $baseProfile->department_id,
            'location_id' => $baseProfile->location_id,
            'employee_code' => 'TST-'.$user->id,
            'hired_at' => '2026-01-01',
            'is_active' => true,
        ]);

        return $user->fresh(['employeeProfile']);
    }

    private function employeeUser(): User
    {
        return User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
    }

    private function adminUser(): User
    {
        return User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
    }
}
