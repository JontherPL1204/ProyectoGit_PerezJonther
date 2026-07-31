<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_employee_account(): void
    {
        $this->seed();

        $response = $this->post(route('register.store'), [
            'name' => 'Nueva Persona',
            'email' => 'nueva.persona@example.com',
            'password' => 'ClaveNueva123!',
            'password_confirmation' => 'ClaveNueva123!',
        ]);

        $user = User::where('email', 'nueva.persona@example.com')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertFalse($user->can_manage_company_rules);
        $this->assertFalse($user->can_view_medical_attachments);
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('leave_allowances', [
            'employee_profile_id' => $user->employeeProfile->id,
            'balance_code' => 'VACATIONS',
            'assigned_units' => 15,
        ]);
    }

    public function test_first_registered_account_becomes_admin_on_empty_database(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Primer Admin',
            'email' => 'primer.admin@example.com',
            'password' => 'ClaveNueva123!',
            'password_confirmation' => 'ClaveNueva123!',
        ]);

        $user = User::where('email', 'primer.admin@example.com')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->can_manage_company_rules);
        $this->assertTrue($user->can_view_medical_attachments);
        $this->assertDatabaseHas('organizations', ['slug' => 'n-woffu-prime']);
        $this->assertDatabaseHas('company_settings', [
            'organization_id' => $user->organization_id,
            'annual_vacation_days' => 15,
            'vacation_notice_days' => 30,
        ]);
    }
}
