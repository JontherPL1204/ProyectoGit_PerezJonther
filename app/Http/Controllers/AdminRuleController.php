<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\LeaveType;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRuleController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function edit(Request $request): View
    {
        $this->authorizeRules($request);

        return view('admin.rules', [
            'settings' => CompanySetting::where('organization_id', $request->user()->organization_id)->firstOrFail(),
            'leaveTypes' => LeaveType::where('organization_id', $request->user()->organization_id)
                ->orderBy('position')
                ->get(),
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

        return back()->with('status', 'Reglas actualizadas correctamente.');
    }

    private function authorizeRules(Request $request): void
    {
        abort_unless($request->user()?->canManageCompanyRules(), 403);
    }
}
