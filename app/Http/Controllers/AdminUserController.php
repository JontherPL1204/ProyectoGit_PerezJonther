<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        $this->authorizeUserManagement($request);

        $search = trim((string) $request->query('q', ''));

        $users = User::with(['employeeProfile.department'])
            ->where('organization_id', $request->user()->organization_id)
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.$search.'%';
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('name', 'like', $needle)
                        ->orWhere('email', 'like', $needle);
                });
            })
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('admin.users', compact('search', 'users'));
    }

    public function promoteByEmail(Request $request): RedirectResponse
    {
        $this->authorizeUserManagement($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
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

        $this->applyPermissionUpdates($request, $target, [
            'role' => 'admin',
            'can_manage_company_rules' => $request->boolean('can_manage_company_rules'),
            'can_view_medical_attachments' => $request->boolean('can_view_medical_attachments'),
        ], 'Permisos asignados por correo.');

        return back()->with('status', 'Permisos de administrador actualizados para '.$target->name.'.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserManagement($request);
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $request->validate([
            'is_admin' => ['nullable', 'boolean'],
            'can_manage_company_rules' => ['nullable', 'boolean'],
            'can_view_medical_attachments' => ['nullable', 'boolean'],
        ]);

        $isAdmin = $request->boolean('is_admin');
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
            'role' => $isAdmin ? 'admin' : 'user',
            'can_manage_company_rules' => $canManageRules,
            'can_view_medical_attachments' => $canViewMedical,
        ], 'Permisos actualizados desde Equipo.');

        return back()->with('status', 'Permisos actualizados para '.$user->name.'.');
    }

    private function authorizeUserManagement(Request $request): void
    {
        abort_unless($request->user()?->canManageCompanyRules(), 403);
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
