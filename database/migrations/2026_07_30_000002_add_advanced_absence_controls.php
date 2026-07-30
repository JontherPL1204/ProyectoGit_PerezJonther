<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->boolean('auto_approve')->default(false)->after('requires_approval');
            $table->boolean('allow_half_day')->default(false)->after('auto_approve');
            $table->unsignedInteger('monthly_limit_units')->nullable()->after('max_units');
            $table->unsignedInteger('yearly_limit_units')->nullable()->after('monthly_limit_units');
            $table->unsignedTinyInteger('approval_level_count')->default(1)->after('yearly_limit_units');

            $table->index(['organization_id', 'department_id']);
        });

        Schema::table('request_attachments', function (Blueprint $table): void {
            $table->string('justification_status')->default('received')->after('is_medical');
            $table->timestamp('reviewed_at')->nullable()->after('justification_status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();

            $table->index(['organization_id', 'justification_status']);
        });
    }

    public function down(): void
    {
        Schema::table('request_attachments', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['organization_id', 'justification_status']);
            $table->dropColumn(['justification_status', 'reviewed_at', 'reviewed_by']);
        });

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['organization_id', 'department_id']);
            $table->dropColumn([
                'department_id',
                'auto_approve',
                'allow_half_day',
                'monthly_limit_units',
                'yearly_limit_units',
                'approval_level_count',
            ]);
        });
    }
};
