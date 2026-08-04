<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\EmployeeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private readonly EmployeeAccountService $accounts) {}

    public function show(string $token): View
    {
        return view('auth.invitation', [
            'invitation' => $this->findInvitation($token),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        if (! $this->canAccept($invitation)) {
            return back()->withErrors(['invitation' => $this->unavailableMessage($invitation)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
        ], [
            'name.required' => 'Escribe tu nombre.',
            'password.required' => 'Crea una contrasena.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
        ]);

        if (User::whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])->exists()) {
            throw ValidationException::withMessages([
                'invitation' => 'Ya existe una cuenta con este correo. Pide una invitacion para otro correo o inicia sesion.',
            ]);
        }

        $user = $this->accounts->registerInvited(
            $invitation->organization,
            [
                'name' => trim((string) $data['name']),
                'email' => $invitation->email,
                'password' => $data['password'],
            ],
            [
                'role' => $invitation->initial_role,
                'can_manage_company_rules' => $invitation->can_manage_company_rules,
                'can_view_medical_attachments' => $invitation->can_view_medical_attachments,
            ],
            $invitation->creator,
        );

        $user->forceFill([
            'timezone' => $data['timezone'] ?? $user->timezoneName(),
        ])->save();

        $invitation->update([
            'status' => TeamInvitation::STATUS_ACCEPTED,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('employee_profile_id', $user->employeeProfile?->id);
        $request->session()->put('employee_department_id', $user->employeeProfile?->department_id);

        return redirect()->route('dashboard')->with('status', 'Invitacion aceptada. Ya formas parte del equipo.');
    }

    private function findInvitation(string $token): ?TeamInvitation
    {
        return TeamInvitation::with(['organization', 'creator'])
            ->where('token_hash', TeamInvitation::tokenHash($token))
            ->first();
    }

    private function canAccept(?TeamInvitation $invitation): bool
    {
        return $invitation
            && $invitation->status === TeamInvitation::STATUS_PENDING
            && ! $invitation->isExpired();
    }

    private function unavailableMessage(?TeamInvitation $invitation): string
    {
        if (! $invitation) {
            return 'Esta invitacion no existe o el enlace no es valido.';
        }

        if ($invitation->status === TeamInvitation::STATUS_ACCEPTED) {
            return 'Esta invitacion ya fue utilizada.';
        }

        if ($invitation->status === TeamInvitation::STATUS_REVOKED) {
            return 'Esta invitacion fue revocada por el equipo.';
        }

        if ($invitation->isExpired()) {
            return 'Esta invitacion caduco. Pide que te envien una nueva.';
        }

        return 'Esta invitacion no esta disponible.';
    }
}
