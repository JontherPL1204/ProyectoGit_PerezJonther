<?php

namespace App\Http\Controllers;

use App\Services\EmployeeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly EmployeeAccountService $accounts,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function create(): View|RedirectResponse
    {
        if (! config('auth.registration_enabled', true)) {
            abort(404);
        }

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera un momento antes de volver a probar.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden.',
            ]);
        }

        $user = $request->user();

        if (! $user?->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta esta desactivada.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->rememberEmployeeProfile($request);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! config('auth.registration_enabled', true)) {
            abort(404);
        }

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Escribe tu nombre.',
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'El correo no tiene un formato valido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'Escribe una contrasena.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
        ]);

        $user = $this->accounts->register($data);

        Auth::login($user);
        $request->session()->regenerate();
        $this->rememberEmployeeProfile($request);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('dashboard')->with('status', 'Cuenta creada correctamente.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function rememberEmployeeProfile(Request $request): void
    {
        $profile = $request->user()?->employeeProfile()->first();

        if ($profile) {
            $request->session()->put('employee_profile_id', $profile->id);
            $request->session()->put('employee_department_id', $profile->department_id);
        }
    }
}
