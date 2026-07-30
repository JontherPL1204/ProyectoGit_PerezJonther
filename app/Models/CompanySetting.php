<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = [
        'organization_id',
        'annual_vacation_days',
        'vacation_notice_days',
        'allow_negative_balance',
        'negative_balance_limit_units',
        'pending_requests_reserve_balance',
        'default_notification_channel',
        'admin_can_view_medical_attachments',
        'medical_attachment_audit_required',
        'approved_request_requires_cancel_flow',
        'prorate_vacations',
        'carry_over_unused_balance',
        'medical_documents_retention_policy',
        'medical_documents_retention_days',
        'period_start_month',
        'period_start_day',
    ];

    protected function casts(): array
    {
        return [
            'allow_negative_balance' => 'boolean',
            'pending_requests_reserve_balance' => 'boolean',
            'admin_can_view_medical_attachments' => 'boolean',
            'medical_attachment_audit_required' => 'boolean',
            'approved_request_requires_cancel_flow' => 'boolean',
            'prorate_vacations' => 'boolean',
            'carry_over_unused_balance' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
