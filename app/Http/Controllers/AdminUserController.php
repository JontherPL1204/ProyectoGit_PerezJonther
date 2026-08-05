<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\OrganizationDataCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly OrganizationDataCache $dataCache,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeUserManagement($request);

        $search = trim((string) $request->query('q', ''));

        $allUsers = $this->dataCache->users($request->user()->organization_id);
        $visibleUsers = $allUsers
            ->filter(fn ($user) => $user->status === User::STATUS_ACTIVE && $user->deactivated_at === null)
            ->values();
        $teamCounts = [
            'visible' => $visibleUsers->count(),
            'admins' => $visibleUsers->where('role', User::ROLE_ADMIN)->count(),
            'developers' => $visibleUsers->where('role', User::ROLE_DEVELOPER)->count(),
            'inactive' => $allUsers->count() - $visibleUsers->count(),
        ];
        $users = $visibleUsers;

        if ($search !== '') {
            $needle = Str::lower($search);
            $users = $users
                ->filter(fn ($user) => str_contains(Str::lower($user->name), $needle)
                    || str_contains(Str::lower($user->email), $needle))
                ->values();
        }

        $positions = $this->dataCache->jobPositions($request->user()->organization_id);

        return view('admin.users', compact('positions', 'search', 'teamCounts', 'users'));
    }

    public function promoteByEmail(Request $request): RedirectResponse
    {
        $this->authorizeUserManagement($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'in:user,admin,developer'],
            'can_manage_company_rules' => ['nullable', 'boolean'],
            'can_view_medical_attachments' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'Escribe el correo de la persona.',
            'email.email' => 'El correo no tiene un formato valido.',
        ]);

        $target = User::where('organization_id', $request->user()->organization_id)
            ->whereRaw('LOWER(email) = ?', [Str::lower($data['email'])])
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'email' => 'No encontre una persona de esta empresa con ese correo. Primero debe existir como usuario.',
            ]);
        }

        $role = $data['role'] ?? User::ROLE_ADMIN;
        $isAdmin = $role === User::ROLE_ADMIN;

        $this->applyPermissionUpdates($request, $target, [
            'role' => $role,
            'can_manage_company_rules' => $isAdmin && $request->boolean('can_manage_company_rules'),
            'can_view_medical_attachments' => $isAdmin && $request->boolean('can_view_medical_attachments'),
        ], 'Permisos asignados por correo.');
        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()
            ->with('status', 'Permiso '.$this->roleLabel($role).' actualizado para '.$target->name.'.')
            ->with('open_user_id', $target->id);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserManagement($request);
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $request->validate([
            'role' => ['nullable', 'in:user,admin,developer'],
            'is_admin' => ['nullable', 'boolean'],
            'can_manage_company_rules' => ['nullable', 'boolean'],
            'can_view_medical_attachments' => ['nullable', 'boolean'],
        ]);

        $role = $request->input('role');
        $role = is_string($role) ? $role : ($request->boolean('is_admin') ? User::ROLE_ADMIN : User::ROLE_USER);
        $isAdmin = $role === User::ROLE_ADMIN;
        $canManageRules = $isAdmin && $request->boolean('can_manage_company_rules');
        $canViewMedical = $isAdmin && $request->boolean('can_view_medical_attachments');

        if ($user->is($request->user()) && ! $isAdmin) {
            throw ValidationException::withMessages([
                'is_admin' => 'No puedes quitarte tu propio acceso de administrador.',
            ]);
        }

        if ($user->is($request->user()) && ! $canManageRules) {
            throw ValidationException::withMessages([
                'can_manage_company_rules' => 'No puedes quitarte tu propio permiso para gestionar permisos y reglas.',
            ]);
        }

        $this->applyPermissionUpdates($request, $user, [
            'role' => $role,
            'can_manage_company_rules' => $canManageRules,
            'can_view_medical_attachments' => $canViewMedical,
        ], 'Permisos actualizados desde Equipo.');
        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()
            ->with('status', 'Permisos actualizados para '.$user->name.'.')
            ->with('open_user_id', $user->id);
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserManagement($request);
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'No puedes ocultar tu propia cuenta.',
            ]);
        }

        if ($user->canManageCompanyRules() && $this->activeRuleManagerCount($user->organization_id) <= 1) {
            throw ValidationException::withMessages([
                'user' => 'No puedes ocultar la ultima cuenta que puede gestionar reglas y equipo.',
            ]);
        }

        if (! $user->isActive()) {
            return back()->with('status', $user->name.' ya estaba oculto del equipo.');
        }

        $deactivatedAt = now();

        DB::transaction(function () use ($deactivatedAt, $request, $user): void {
            $this->audit->ruleChange(
                $user->organization_id,
                'users',
                $user->id,
                'status',
                $user->status,
                User::STATUS_INACTIVE,
                $request->user(),
                'Trabajador ocultado desde Equipo.',
                $request,
            );

            $this->audit->ruleChange(
                $user->organization_id,
                'users',
                $user->id,
                'deactivated_at',
                $user->deactivated_at,
                $deactivatedAt,
                $request->user(),
                'Trabajador ocultado desde Equipo.',
                $request,
            );

            $user->forceFill([
                'status' => User::STATUS_INACTIVE,
                'deactivated_at' => $deactivatedAt,
                'remember_token' => null,
            ])->save();

            $profile = $user->employeeProfile()->first();

            if ($profile) {
                $this->audit->ruleChange(
                    $user->organization_id,
                    'employee_profiles',
                    $profile->id,
                    'is_active',
                    $profile->is_active,
                    false,
                    $request->user(),
                    'Trabajador ocultado desde Equipo.',
                    $request,
                );

                $profile->forceFill(['is_active' => false])->save();
            }
        });

        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()->with('status', $user->name.' fue ocultado del equipo. Sus datos historicos se conservan.');
    }

    private function authorizeUserManagement(Request $request): void
    {
        abort_unless($request->user()?->canManageCompanyRules(), 403);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN => 'administrador',
            User::ROLE_DEVELOPER => 'desarrollador',
            default => 'empleado',
        };
    }

    private function activeRuleManagerCount(int $organizationId): int
    {
        return User::where('organization_id', $organizationId)
            ->where('role', User::ROLE_ADMIN)
            ->where('can_manage_company_rules', true)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNull('deactivated_at')
            ->count();
    }

    /**
     * @param  array{role:string,can_manage_company_rules:bool,can_view_medical_attachments:bool}  $updates
     */
    private function applyPermissionUpdates(Request $request, User $target, array $updates, string $comment): void
    {
        foreach ($updates as $field => $value) {
            if ($target->{$field} != $value) {
                $this->audit->ruleChange(
                    $target->organization_id,
                    'users',
                    $target->id,
                    $field,
                    $target->{$field},
                    $value,
                    $request->user(),
                    $comment,
                    $request,
                );
            }
        }

        $target->update($updates);
    }
}
