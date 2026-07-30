<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    protected $fillable = [
        'organization_id',
        'event',
        'recipient_type',
        'is_active',
        'subject_template',
        'body_template',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
