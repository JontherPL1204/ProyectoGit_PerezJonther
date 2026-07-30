<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('mode')->default('internal');
            $table->string('timezone')->default('America/Guayaquil');
            $table->string('locale')->default('es');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('timezone')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('user')->after('password');
            $table->string('status')->default('active')->after('role');
            $table->boolean('can_manage_company_rules')->default(false)->after('status');
            $table->boolean('can_view_medical_attachments')->default(false)->after('can_manage_company_rules');
            $table->timestamp('last_login_at')->nullable()->after('can_view_medical_attachments');
            $table->timestamp('deactivated_at')->nullable()->after('last_login_at');

            $table->index(['organization_id', 'role', 'status']);
        });

        Schema::create('employee_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->date('hired_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('user_id');
            $table->unique(['organization_id', 'employee_code']);
            $table->index(['organization_id', 'department_id', 'location_id']);
        });

        Schema::create('company_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('annual_vacation_days')->default(15);
            $table->unsignedInteger('vacation_notice_days')->default(30);
            $table->boolean('allow_negative_balance')->default(false);
            $table->integer('negative_balance_limit_units')->nullable();
            $table->boolean('pending_requests_reserve_balance')->default(true);
            $table->string('default_notification_channel')->default('email');
            $table->boolean('admin_can_view_medical_attachments')->default(true);
            $table->boolean('medical_attachment_audit_required')->default(true);
            $table->boolean('approved_request_requires_cancel_flow')->default(true);
            $table->boolean('prorate_vacations')->default(true);
            $table->boolean('carry_over_unused_balance')->default(true);
            $table->string('medical_documents_retention_policy')->default('retain');
            $table->unsignedInteger('medical_documents_retention_days')->nullable();
            $table->unsignedTinyInteger('period_start_month')->default(1);
            $table->unsignedTinyInteger('period_start_day')->default(1);
            $table->timestamps();

            $table->unique('organization_id');
        });

        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('work_schedule_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_working_day')->default(false);
            $table->unsignedInteger('work_minutes')->default(0);
            $table->timestamps();

            $table->unique(['work_schedule_id', 'weekday']);
        });

        Schema::create('employee_schedule_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'valid_from', 'valid_until']);
        });

        Schema::create('holiday_calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('holiday_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('scope')->default('company');
            $table->timestamps();

            $table->unique(['holiday_calendar_id', 'holiday_date']);
        });

        Schema::create('employee_calendar_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('holiday_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'valid_from', 'valid_until']);
        });

        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('unit')->default('DAYS');
            $table->boolean('consumes_balance')->default(false);
            $table->string('balance_code')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->string('attachment_requirement')->default('none');
            $table->boolean('is_medical')->default(false);
            $table->unsignedInteger('notice_value')->default(0);
            $table->string('notice_unit')->default('days');
            $table->unsignedInteger('min_units')->nullable();
            $table->unsignedInteger('max_units')->nullable();
            $table->boolean('allow_retroactive')->default(false);
            $table->boolean('visible_to_employees')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active', 'visible_to_employees']);
        });

        Schema::create('leave_allowances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('balance_code')->default('VACATIONS');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('assigned_units')->default(0);
            $table->timestamps();

            $table->unique(['employee_profile_id', 'balance_code', 'period_start']);
            $table->index(['organization_id', 'period_start', 'period_end']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('unit')->default('DAYS');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('requested_units')->default(0);
            $table->string('status')->default('PENDING');
            $table->text('user_comment')->nullable();
            $table->text('admin_comment')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('requested_cancel_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status', 'start_date']);
            $table->index(['employee_profile_id', 'status', 'start_date', 'end_date']);
            $table->index(['leave_type_id', 'created_at']);
        });

        Schema::create('leave_request_calculation_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->boolean('is_working_day')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->integer('computed_units')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'work_date']);
        });

        Schema::create('leave_balance_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_allowance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type');
            $table->integer('amount');
            $table->string('idempotency_key')->unique();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['leave_allowance_id', 'created_at']);
            $table->index(['organization_id', 'movement_type']);
        });

        Schema::create('request_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('is_medical')->default(false);
            $table->string('checksum')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_medical']);
        });

        Schema::create('request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['leave_request_id', 'created_at']);
            $table->index(['organization_id', 'action']);
        });

        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('recipient_type');
            $table->boolean('is_active')->default(true);
            $table->string('subject_template');
            $table->text('body_template');
            $table->timestamps();

            $table->unique(['organization_id', 'event', 'recipient_type']);
        });

        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['organization_id', 'event']);
        });

        Schema::create('rule_change_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('field_name');
            $table->text('previous_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'entity_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_change_events');
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('request_events');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('leave_balance_movements');
        Schema::dropIfExists('leave_request_calculation_days');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_allowances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employee_calendar_assignments');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('employee_schedule_assignments');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('employee_profiles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'role', 'status']);
            $table->dropColumn([
                'organization_id',
                'role',
                'status',
                'can_manage_company_rules',
                'can_view_medical_attachments',
                'last_login_at',
                'deactivated_at',
            ]);
        });

        Schema::dropIfExists('locations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('organizations');
    }
};
