<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class JobPosition extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'normalized_name',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public static function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->lower()->squish()->toString();
    }

    /**
     * @return list<string>
     */
    public static function defaultNames(): array
    {
        return [
            'Frontend Developer',
            'Backend Developer',
            'SEO',
            'UI/UX Designer',
            'AI Consulting',
            'Project Management',
            'CTO',
            'CMO',
            'RR. HH.',
            'Social Media',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employeeProfiles(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeProfile::class)
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }
}
