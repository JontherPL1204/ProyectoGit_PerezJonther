<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeamInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'organization_id',
        'email',
        'token_hash',
        'status',
        'initial_role',
        'can_manage_company_rules',
        'can_view_medical_attachments',
        'created_by',
        'accepted_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'last_sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'can_manage_company_rules' => 'boolean',
            'can_view_medical_attachments' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newPlainToken(): string
    {
        return Str::random(64);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at->lessThan($now ?? now());
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_ACCEPTED) {
            return 'Aceptada';
        }

        if ($this->status === self::STATUS_REVOKED) {
            return 'Revocada';
        }

        if ($this->isExpired()) {
            return 'Caducada';
        }

        return 'Pendiente';
    }
}
