<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\LeaveType;
use App\Services\AuditService;
use App\Services\OrganizationDataCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminRuleController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly OrganizationDataCache $dataCache,
    ) {}

    public function edit(Request $request): View
    {
        $this->authorizeRules($request);

        return view('admin.rules', [
            'settings' => $this->dataCache->settings($request->user()->organization_id),
            'leaveTypes' => $this->dataCache->leaveTypes($request->user()->organization_id, true),
            'departments' => $this->dataCache->departments($request->user()->organization_id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeRules($request);

        $data = $request->validate([
            'annual_vacation_days' => ['required', 'integer', 'min:0', 'max:365'],
            'vacation_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'allow_negative_balance' => ['nullable', 'boolean'],
            'pending_requests_reserve_balance' => ['nullable', 'boolean'],
            'admin_can_view_medical_attachments' => ['nullable', 'boolean'],
            'medical_attachment_audit_required' => ['nullable', 'boolean'],
            'approved_request_requires_cancel_flow' => ['nullable', 'boolean'],
            'prorate_vacations' => ['nullable', 'boolean'],
            'carry_over_unused_balance' => ['nullable', 'boolean'],
            'medical_documents_retention_policy' => ['required', 'in:retain,days'],
            'medical_documents_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'leave_types' => ['nullable', 'array'],
            'leave_types.*.name' => ['required', 'string', 'max:255'],
            'leave_types.*.is_active' => ['nullable', 'boolean'],
            'leave_types.*.visible_to_employees' => ['nullable', 'boolean'],
            'leave_types.*.requires_approval' => ['nullable', 'boolean'],
            'leave_types.*.auto_approve' => ['nullable', 'boolean'],
            'leave_types.*.allow_half_day' => ['nullable', 'boolean'],
            'leave_types.*.allow_retroactive' => ['nullable', 'boolean'],
            'leave_types.*.attachment_requirement' => ['required', 'in:none,optional,required'],
            'leave_types.*.notice_value' => ['nullable', 'integer', 'min:0', 'max:365'],
            'leave_types.*.min_units' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'leave_types.*.max_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'leave_types.*.monthly_limit_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'leave_types.*.yearly_limit_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'leave_types.*.approval_level_count' => ['required', 'integer', 'min:1', 'max:3'],
            'leave_types.*.department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('organization_id', $request->user()->organization_id),
            ],
            'change_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings = CompanySetting::where('organization_id', $request->user()->organization_id)->firstOrFail();
        $updates = [
            'annual_vacation_days' => (int) $data['annual_vacation_days'],
            'vacation_notice_days' => (int) $data['vacation_notice_days'],
            'allow_negative_balance' => $request->boolean('allow_negative_balance'),
            'pending_requests_reserve_balance' => $request->boolean('pending_requests_reserve_balance'),
            'admin_can_view_medical_attachments' => $request->boolean('admin_can_view_medical_attachments'),
            'medical_attachment_audit_required' => $request->boolean('medical_attachment_audit_required'),
            'approved_request_requires_cancel_flow' => $request->boolean('approved_request_requires_cancel_flow'),
            'prorate_vacations' => $request->boolean('prorate_vacations'),
            'carry_over_unused_balance' => $request->boolean('carry_over_unused_balance'),
            'medical_documents_retention_policy' => $data['medical_documents_retention_policy'],
            'medical_documents_retention_days' => $data['medical_documents_retention_policy'] === 'days'
                ? (int) $data['medical_documents_retention_days']
                : null,
        ];

        $sensitive = [
            'annual_vacation_days',
            'vacation_notice_days',
            'allow_negative_balance',
            'admin_can_view_medical_attachments',
            'medical_documents_retention_policy',
            'medical_documents_retention_days',
        ];

        $changedSensitive = collect($updates)
            ->filter(fn ($value, $field) => $settings->{$field} != $value && in_array($field, $sensitive, true))
            ->isNotEmpty();

        if ($changedSensitive && trim((string) ($data['change_comment'] ?? '')) === '') {
            return back()
                ->withInput()
                ->withErrors(['change_comment' => 'Los cambios sensibles requieren comentario obligatorio.']);
        }

        foreach ($updates as $field => $value) {
            if ($settings->{$field} != $value) {
                $this->audit->ruleChange(
                    $settings->organization_id,
                    'company_settings',
                    $settings->id,
                    $field,
                    $settings->{$field},
                    $value,
                    $request->user(),
                    $data['change_comment'] ?? null,
                    $request,
                );
            }
        }

        $settings->update($updates);

        LeaveType::where('organization_id', $settings->organization_id)
            ->where('code', 'VACATIONS')
            ->update([
                'notice_value' => $settings->vacation_notice_days,
                'max_units' => $settings->annual_vacation_days,
            ]);

        $this->updateLeaveTypes($request, $settings, $data);
        $this->dataCache->forgetOrganization($settings->organization_id);

        return back()->with('status', 'Reglas actualizadas correctamente.');
    }

    private function authorizeRules(Request $request): void
    {
        abort_unless($request->user()?->canManageCompanyRules(), 403);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function updateLeaveTypes(Request $request, CompanySetting $settings, array $data): void
    {
        $payload = $data['leave_types'] ?? [];

        if (! is_array($payload) || $payload === []) {
            return;
        }

        $types = LeaveType::where('organization_id', $settings->organization_id)
            ->get()
            ->keyBy('id');

        foreach ($payload as $id => $values) {
            $type = $types->get((int) $id);

            if (! $type || ! is_array($values)) {
                continue;
            }

            $minUnits = $this->nullableInteger($values['min_units'] ?? null);
            $maxUnits = $type->code === 'VACATIONS'
                ? $settings->annual_vacation_days
                : $this->nullableInteger($values['max_units'] ?? null);
            $monthlyLimit = $this->nullableInteger($values['monthly_limit_units'] ?? null);
            $yearlyLimit = $this->nullableInteger($values['yearly_limit_units'] ?? null);

            if ($minUnits !== null && $maxUnits !== null && $maxUnits < $minUnits) {
                throw ValidationException::withMessages([
                    "leave_types.$id.max_units" => 'El maximo no puede ser menor que el minimo en '.$type->name.'.',
                ]);
            }

            if ($monthlyLimit !== null && $yearlyLimit !== null && $yearlyLimit < $monthlyLimit) {
                throw ValidationException::withMessages([
                    "leave_types.$id.yearly_limit_units" => 'El limite anual no puede ser menor que el limite mensual en '.$type->name.'.',
                ]);
            }

            $updates = [
                'department_id' => $this->nullableInteger($values['department_id'] ?? null),
                'name' => trim((string) $values['name']),
                'is_active' => $request->boolean("leave_types.$id.is_active"),
                'visible_to_employees' => $request->boolean("leave_types.$id.visible_to_employees"),
                'requires_approval' => $request->boolean("leave_types.$id.requires_approval"),
                'auto_approve' => $request->boolean("leave_types.$id.auto_approve"),
                'allow_half_day' => $request->boolean("leave_types.$id.allow_half_day"),
                'allow_retroactive' => $request->boolean("leave_types.$id.allow_retroactive"),
                'attachment_requirement' => (string) $values['attachment_requirement'],
                'notice_value' => $type->code === 'VACATIONS'
                    ? $settings->vacation_notice_days
                    : (int) ($values['notice_value'] ?? 0),
                'min_units' => $minUnits,
                'max_units' => $maxUnits,
                'monthly_limit_units' => $monthlyLimit,
                'yearly_limit_units' => $yearlyLimit,
                'approval_level_count' => (int) ($values['approval_level_count'] ?? 1),
            ];

            foreach ($updates as $field => $value) {
                if ($type->{$field} != $value) {
                    $this->audit->ruleChange(
                        $settings->organization_id,
                        'leave_types',
                        $type->id,
                        $field,
                        $type->{$field},
                        $value,
                        $request->user(),
                        $data['change_comment'] ?? null,
                        $request,
                    );
                }
            }

            $type->update($updates);
        }
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
