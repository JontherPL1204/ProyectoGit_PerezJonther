<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function currentEmployeeProfileId(Request $request): int
    {
        $profileId = $request->session()->get('employee_profile_id');

        if ($profileId) {
            return (int) $profileId;
        }

        $profileId = EmployeeProfile::where('user_id', $request->user()->id)->value('id');

        abort_unless($profileId, 403, 'El usuario no tiene perfil de empleado.');

        $request->session()->put('employee_profile_id', $profileId);

        return (int) $profileId;
    }

    protected function currentEmployeeDepartmentId(Request $request): ?int
    {
        if ($request->session()->has('employee_department_id')) {
            $departmentId = $request->session()->get('employee_department_id');

            return $departmentId === null ? null : (int) $departmentId;
        }

        $departmentId = EmployeeProfile::where('user_id', $request->user()->id)->value('department_id');
        $request->session()->put('employee_department_id', $departmentId);

        return $departmentId === null ? null : (int) $departmentId;
    }
}
