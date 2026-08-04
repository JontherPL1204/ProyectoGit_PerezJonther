<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'role',
        'status',
        'timezone',
        'can_manage_company_rules',
        'can_view_medical_attachments',
        'last_login_at',
        'deactivated_at',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deactivated_at === null;
    }

    public function canManageCompanyRules(): bool
    {
        return $this->isAdmin() && $this->can_manage_company_rules;
    }

    public function canViewMedicalAttachments(): bool
    {
        return $this->isAdmin() && $this->can_view_medical_attachments;
    }

    public function timezoneName(): string
    {
        return $this->timezone
            ?: $this->organization?->timezone
            ?: config('app.timezone', 'UTC');
    }

    public function formatDateTime(CarbonInterface|string|null $value, string $format = 'd/m/Y H:i'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ($value instanceof CarbonInterface ? $value : CarbonImmutable::parse($value))
            ->timezone($this->timezoneName())
            ->format($format);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_manage_company_rules' => 'boolean',
            'can_view_medical_attachments' => 'boolean',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }
}
