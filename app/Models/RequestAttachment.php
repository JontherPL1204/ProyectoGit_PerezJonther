<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'leave_request_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'is_medical',
        'checksum',
    ];

    protected function casts(): array
    {
        return ['is_medical' => 'boolean'];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
