<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'timezones' => timezone_identifiers_list(),
            'currentTimezone' => $request->user()->timezoneName(),
        ]);
    }

    public function updateTimezone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ], [
            'timezone.required' => 'Selecciona una zona horaria.',
            'timezone.in' => 'La zona horaria seleccionada no es valida.',
        ]);

        $request->user()->forceFill(['timezone' => $data['timezone']])->save();

        return back()->with('status', 'Zona horaria actualizada.');
    }

    public function detectTimezone(Request $request): Response
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ]);

        if ($request->user()->timezone !== $data['timezone']) {
            $request->user()->forceFill(['timezone' => $data['timezone']])->save();
        }

        return response()->noContent();
    }
}
