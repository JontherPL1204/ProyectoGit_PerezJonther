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
        'justification_status',
        'reviewed_at',
        'reviewed_by',
        'checksum',
        'file_content',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'file_content',
    ];

    protected function casts(): array
    {
        return [
            'is_medical' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function justificationLabel(): string
    {
        return match ($this->justification_status) {
            'pending' => 'Pendiente de justificar',
            'reviewed' => 'Justificante validado',
            default => 'Justificante recibido',
        };
    }

    public function fileContent(): ?string
    {
        if ($this->file_content === null || $this->file_content === '') {
            return null;
        }

        if (is_resource($this->file_content)) {
            $content = stream_get_contents($this->file_content);

            return is_string($content) && $content !== '' ? $content : null;
        }

        return is_string($this->file_content) ? $this->file_content : (string) $this->file_content;
    }
}
