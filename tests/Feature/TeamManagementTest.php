<?php

namespace Tests\Feature;

use App\Models\JobPosition;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_assign_edit_and_delete_custom_positions(): void
    {
        $this->seed();

        $admin = $this->adminUser();
        $employee = $this->employeeUser();
        $frontend = JobPosition::where('name', 'Frontend Developer')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('team-member-card', false)
            ->assertSee('Frontend Developer')
            ->assertSee('permission-dropdown', false)
            ->assertSee('position-dropdown', false)
            ->assertSee('position-options', false)
            ->assertSee('Guardar puestos')
            ->assertDontSee('Crear y asignar puesto');

        $this->actingAs($admin)
            ->get(route('admin.management.index'))
            ->assertOk()
            ->assertSee('permission-dropdown', false)
            ->assertSee('Crear puesto')
            ->assertSee('position-admin-dropdown', false)
            ->assertSee('Puestos creados');

        $this->actingAs($admin)
            ->post(route('admin.management.positions.store'), ['name' => 'QA Analyst'])
            ->assertSessionHas('status');

        $qa = JobPosition::where('name', 'QA Analyst')->firstOrFail();
        $this->assertFalse($qa->is_system);

        $this->actingAs($admin)
            ->post(route('admin.users.positions.update', $employee), [
                'job_position_ids' => [$frontend->id],
                'new_position_name' => 'DevOps Engineer',
            ])
            ->assertSessionHas('status', 'Puesto "DevOps Engineer" creado y asignado para '.$employee->name.'.')
            ->assertSessionHas('open_user_id', $employee->id);

        $employee->employeeProfile->refresh();
        $assignedNames = $employee->employeeProfile->jobPositions()->pluck('name')->sort()->values()->all();
        $this->assertSame(['DevOps Engineer', 'Frontend Developer'], $assignedNames);

        $devOps = JobPosition::where('name', 'DevOps Engineer')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.users.positions.update', $employee), [
                'job_position_ids' => [$frontend->id, $devOps->id],
                'new_position_name' => 'frontend developer',
            ])
            ->assertSessionHas('status');

        $this->assertSame(2, $employee->employeeProfile->jobPositions()->count());
        $this->assertSame(1, JobPosition::where('normalized_name', 'frontend developer')->count());

        $this->actingAs($admin)
            ->post(route('admin.management.positions.update', $qa), ['name' => 'QA Specialist'])
            ->assertSessionHas('status');

        $qa->refresh();
        $this->assertSame('QA Specialist', $qa->name);

        $this->actingAs($admin)
            ->post(route('admin.management.positions.destroy', $devOps))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('job_positions', ['id' => $devOps->id]);
        $this->assertDatabaseMissing('employee_profile_job_position', ['job_position_id' => $devOps->id]);
    }

    public function test_invitations_can_be_created_accepted_revoked_and_report_unavailable_states(): void
    {
        Mail::fake();
        $this->seed();

        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.management.invitations.store'), [
            'email' => 'nueva.persona@example.com',
            'initial_role' => 'admin',
            'can_manage_company_rules' => '1',
            'can_view_medical_attachments' => '1',
        ]);

        $response->assertSessionHas('invite_link');
        $inviteLink = $response->getSession()->get('invite_link');
        $token = basename(parse_url($inviteLink, PHP_URL_PATH));

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSee('Completa tu cuenta.');

        $this->post(route('invitations.accept', $token), [
            'name' => 'Nueva Persona',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'Europe/Madrid',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'nueva.persona@example.com')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->can_manage_company_rules);
        $this->assertSame('Europe/Madrid', $user->timezone);
        $this->assertSame(TeamInvitation::STATUS_ACCEPTED, TeamInvitation::firstWhere('email', 'nueva.persona@example.com')->status);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSee('Esta invitacion ya fue utilizada.');

        $revokedToken = TeamInvitation::newPlainToken();
        $revoked = TeamInvitation::create([
            'organization_id' => $admin->organization_id,
            'email' => 'revocada@example.com',
            'token_hash' => TeamInvitation::tokenHash($revokedToken),
            'status' => TeamInvitation::STATUS_PENDING,
            'initial_role' => 'user',
            'created_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.management.invitations.revoke', $revoked))
            ->assertSessionHas('status');

        $this->get(route('invitations.show', $revokedToken))
            ->assertOk()
            ->assertSee('Esta invitacion fue revocada.');

        $expiredToken = TeamInvitation::newPlainToken();
        TeamInvitation::create([
            'organization_id' => $admin->organization_id,
            'email' => 'caducada@example.com',
            'token_hash' => TeamInvitation::tokenHash($expiredToken),
            'status' => TeamInvitation::STATUS_PENDING,
            'initial_role' => 'user',
            'created_by' => $admin->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('invitations.show', $expiredToken))
            ->assertOk()
            ->assertSee('Esta invitacion caduco.');
    }

    public function test_manager_can_hide_worker_without_deleting_their_data(): void
    {
        $this->seed();

        $admin = $this->adminUser();
        $employee = $this->employeeUser();
        $profileId = $employee->employeeProfile()->value('id');

        $this->actingAs($admin)
            ->post(route('admin.users.deactivate', $employee))
            ->assertSessionHas('status', $employee->name.' fue ocultado del equipo. Sus datos historicos se conservan.');

        $employee->refresh();

        $this->assertSame(User::STATUS_INACTIVE, $employee->status);
        $this->assertNotNull($employee->deactivated_at);
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'email' => $employee->email,
            'status' => User::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $profileId,
            'user_id' => $employee->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('con historial guardado')
            ->assertDontSee($employee->email);
    }

    public function test_manager_cannot_hide_their_own_account(): void
    {
        $this->seed();

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.users.deactivate', $admin))
            ->assertSessionHasErrors('user');

        $this->assertSame(User::STATUS_ACTIVE, $admin->refresh()->status);
    }

    public function test_user_can_update_timezone_and_dates_are_presented_locally(): void
    {
        $this->seed();

        $employee = $this->employeeUser();

        $this->actingAs($employee)
            ->post(route('profile.timezone.update'), ['timezone' => 'Europe/Madrid'])
            ->assertSessionHas('status');

        $employee->refresh();

        $this->assertSame('Europe/Madrid', $employee->timezone);
        $this->assertSame('04/08/2026 14:00', $employee->formatDateTime('2026-08-04 12:00:00'));

        $this->actingAs($employee)
            ->postJson(route('profile.timezone.detect'), ['timezone' => 'America/New_York'])
            ->assertNoContent();

        $this->assertSame('America/New_York', $employee->refresh()->timezone);
    }

    private function adminUser(): User
    {
        return User::where('email', env('SEED_ADMIN_EMAIL', 'admin@n-woffu-prime.local'))->firstOrFail();
    }

    private function employeeUser(): User
    {
        return User::where('email', env('SEED_EMPLOYEE_EMAIL', 'empleado@n-woffu-prime.local'))->firstOrFail();
    }
}
