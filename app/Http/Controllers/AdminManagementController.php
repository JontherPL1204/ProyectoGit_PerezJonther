<?php

namespace App\Http\Controllers;

use App\Models\JobPosition;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrganizationDataCache;
use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminManagementController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly OrganizationDataCache $dataCache,
        private readonly TeamInvitationService $invitations,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeManagement($request);

        $positions = $this->dataCache->jobPositions($request->user()->organization_id);
        $invitations = TeamInvitation::with('creator')
            ->where('organization_id', $request->user()->organization_id)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->simplePaginate(12);

        return view('admin.management', compact('invitations', 'positions'));
    }

    public function storeInvitation(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'initial_role' => ['required', 'in:user,admin'],
            'can_manage_company_rules' => ['nullable', 'boolean'],
            'can_view_medical_attachments' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'Escribe el correo de la persona invitada.',
            'email.email' => 'El correo no tiene un formato valido.',
        ]);

        $email = Str::lower(trim((string) $data['email']));

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese correo. Puedes asignarle permisos desde Equipo.',
            ]);
        }

        if (TeamInvitation::where('organization_id', $request->user()->organization_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', TeamInvitation::STATUS_PENDING)
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una invitacion pendiente para ese correo. Puedes reenviarla desde la lista.',
            ]);
        }

        $token = TeamInvitation::newPlainToken();
        $isAdmin = $data['initial_role'] === 'admin';

        $invitation = TeamInvitation::create([
            'organization_id' => $request->user()->organization_id,
            'email' => $email,
            'token_hash' => TeamInvitation::tokenHash($token),
            'status' => TeamInvitation::STATUS_PENDING,
            'initial_role' => $isAdmin ? 'admin' : 'user',
            'can_manage_company_rules' => $isAdmin && $request->boolean('can_manage_company_rules'),
            'can_view_medical_attachments' => $isAdmin && $request->boolean('can_view_medical_attachments'),
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $url = route('invitations.show', $token);
        $this->invitations->send($invitation->load('organization'), $url);
        $this->audit->ruleChange($invitation->organization_id, 'team_invitations', $invitation->id, 'status', null, 'pending', $request->user(), 'Invitacion creada.', $request);

        return back()
            ->with('status', 'Invitacion creada. Comparte el enlace con la persona invitada.')
            ->with('invite_link', $url);
    }

    public function resendInvitation(Request $request, TeamInvitation $teamInvitation): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizeInvitation($request, $teamInvitation);

        if ($teamInvitation->status !== TeamInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => 'Solo se pueden reenviar invitaciones pendientes.',
            ]);
        }

        $teamInvitation->update([
            'expires_at' => now()->addDays(7),
            'last_error' => null,
        ]);

        $token = $this->invitations->rotateToken($teamInvitation);
        $this->invitations->send($teamInvitation->fresh('organization'), $token['url']);
        $this->audit->ruleChange($teamInvitation->organization_id, 'team_invitations', $teamInvitation->id, 'token_hash', 'anterior', 'renovado', $request->user(), 'Invitacion reenviada.', $request);

        return back()
            ->with('status', 'Invitacion reenviada. El enlace anterior dejo de funcionar.')
            ->with('invite_link', $token['url']);
    }

    public function revokeInvitation(Request $request, TeamInvitation $teamInvitation): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizeInvitation($request, $teamInvitation);

        if ($teamInvitation->status !== TeamInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => 'Esta invitacion ya no esta pendiente.',
            ]);
        }

        $teamInvitation->update([
            'status' => TeamInvitation::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $this->audit->ruleChange($teamInvitation->organization_id, 'team_invitations', $teamInvitation->id, 'status', 'pending', 'revoked', $request->user(), 'Invitacion revocada.', $request);

        return back()->with('status', 'Invitacion revocada correctamente.');
    }

    public function storePosition(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ], [
            'name.required' => 'Escribe el nombre del puesto.',
        ]);

        if (JobPosition::where('organization_id', $request->user()->organization_id)
            ->where('normalized_name', JobPosition::normalizeName($data['name']))
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un puesto con ese nombre.',
            ]);
        }

        $position = $this->createPosition($request, $data['name']);
        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()->with('status', 'Puesto '.$position->name.' creado correctamente.');
    }

    public function updatePosition(Request $request, JobPosition $jobPosition): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizePosition($request, $jobPosition);

        if ($jobPosition->is_system) {
            throw ValidationException::withMessages([
                'position' => 'Los puestos iniciales no se editan. Crea uno personalizado si necesitas otra variante.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $normalizedName = JobPosition::normalizeName($data['name']);

        if (JobPosition::where('organization_id', $request->user()->organization_id)
            ->where('normalized_name', $normalizedName)
            ->where('id', '!=', $jobPosition->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un puesto con ese nombre.',
            ]);
        }

        $previous = $jobPosition->name;
        $jobPosition->update([
            'name' => trim((string) $data['name']),
            'normalized_name' => $normalizedName,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->ruleChange($jobPosition->organization_id, 'job_positions', $jobPosition->id, 'name', $previous, $jobPosition->name, $request->user(), 'Puesto editado.', $request);
        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()->with('status', 'Puesto actualizado correctamente.');
    }

    public function destroyPosition(Request $request, JobPosition $jobPosition): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizePosition($request, $jobPosition);

        if ($jobPosition->is_system) {
            throw ValidationException::withMessages([
                'position' => 'Los puestos iniciales no se eliminan.',
            ]);
        }

        $assignedCount = $jobPosition->employeeProfiles()->count();

        DB::transaction(function () use ($jobPosition, $assignedCount, $request): void {
            $name = $jobPosition->name;
            $jobPosition->employeeProfiles()->detach();
            $jobPosition->delete();

            $this->audit->ruleChange($request->user()->organization_id, 'job_positions', $jobPosition->id, 'deleted', $name, 'eliminado', $request->user(), 'Puesto eliminado. Personas desvinculadas: '.$assignedCount.'.', $request);
        });

        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()->with('status', $assignedCount > 0
            ? 'Puesto eliminado y quitado de '.$assignedCount.' integrante(s).'
            : 'Puesto eliminado correctamente.');
    }

    public function updateUserPositions(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $profile = $user->employeeProfile()->with('jobPositions')->firstOrFail();
        $data = $request->validate([
            'job_position_ids' => ['nullable', 'array'],
            'job_position_ids.*' => [
                'integer',
                Rule::exists('job_positions', 'id')->where('organization_id', $request->user()->organization_id),
            ],
            'new_position_name' => ['nullable', 'string', 'max:120'],
        ]);

        $positionIds = collect($data['job_position_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (filled($data['new_position_name'] ?? null)) {
            $positionIds->push($this->createPosition($request, (string) $data['new_position_name'])->id);
        }

        $previous = $profile->jobPositions->pluck('name')->sort()->values()->implode(', ');

        $profile->jobPositions()->syncWithPivotValues($positionIds->all(), [
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        $current = $profile->fresh('jobPositions')->jobPositions->pluck('name')->sort()->values()->implode(', ');

        if ($previous !== $current) {
            $this->audit->ruleChange($user->organization_id, 'employee_profiles', $profile->id, 'job_positions', $previous, $current, $request->user(), 'Puestos actualizados desde Equipo.', $request);
        }

        $this->dataCache->forgetOrganization($request->user()->organization_id);

        return back()->with('status', 'Puestos actualizados para '.$user->name.'.');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->canManageCompanyRules(), 403);
    }

    private function authorizeInvitation(Request $request, TeamInvitation $invitation): void
    {
        abort_unless($invitation->organization_id === $request->user()->organization_id, 404);
    }

    private function authorizePosition(Request $request, JobPosition $jobPosition): void
    {
        abort_unless($jobPosition->organization_id === $request->user()->organization_id, 404);
    }

    private function createPosition(Request $request, string $name): JobPosition
    {
        $name = trim($name);
        $normalizedName = JobPosition::normalizeName($name);

        if ($normalizedName === '') {
            throw ValidationException::withMessages([
                'name' => 'Escribe el nombre del puesto.',
            ]);
        }

        $position = JobPosition::firstOrCreate(
            [
                'organization_id' => $request->user()->organization_id,
                'normalized_name' => $normalizedName,
            ],
            [
                'name' => $name,
                'is_system' => false,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        );

        if (! $position->wasRecentlyCreated) {
            return $position;
        }

        $this->audit->ruleChange($position->organization_id, 'job_positions', $position->id, 'created', null, $position->name, $request->user(), 'Puesto creado.', $request);

        return $position;
    }
}
