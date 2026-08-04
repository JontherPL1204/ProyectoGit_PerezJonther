<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->after('status');
        });

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('code');
        });

        Schema::create('job_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'normalized_name']);
            $table->index(['organization_id', 'is_system', 'name']);
        });

        Schema::create('employee_profile_job_position', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_profile_id', 'job_position_id'], 'employee_job_position_unique');
        });

        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash')->unique();
            $table->string('status')->default('pending');
            $table->string('initial_role')->default('user');
            $table->boolean('can_manage_company_rules')->default(false);
            $table->boolean('can_view_medical_attachments')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'expires_at']);
            $table->index(['organization_id', 'email']);
        });

        DB::table('leave_types')
            ->whereIn('code', ['VACATIONS', 'MEDICAL', 'PERSONAL', 'TRAINING'])
            ->update(['is_system' => true]);

        foreach (DB::table('organizations')->select('id', 'timezone')->get() as $organization) {
            DB::table('users')
                ->where('organization_id', $organization->id)
                ->whereNull('timezone')
                ->update(['timezone' => $organization->timezone]);

            foreach ($this->defaultPositions() as $position) {
                DB::table('job_positions')->updateOrInsert(
                    [
                        'organization_id' => $organization->id,
                        'normalized_name' => $this->normalize($position),
                    ],
                    [
                        'name' => $position,
                        'is_system' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('employee_profile_job_position');
        Schema::dropIfExists('job_positions');

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }

    /**
     * @return list<string>
     */
    private function defaultPositions(): array
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

    private function normalize(string $name): string
    {
        return Str::of($name)->trim()->lower()->squish()->toString();
    }
};
