<?php

use App\Models\LeaveRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'level'], 'approval_steps_request_level_unique');
            $table->index(['organization_id', 'status', 'level']);
        });

        DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.status', LeaveRequest::STATUS_PENDING)
            ->where('leave_types.requires_approval', true)
            ->where('leave_types.auto_approve', false)
            ->select([
                'leave_requests.id',
                'leave_requests.organization_id',
                'leave_types.approval_level_count',
            ])
            ->orderBy('leave_requests.id')
            ->chunkById(100, function ($requests): void {
                $now = now();

                foreach ($requests as $request) {
                    $levels = max(1, min(3, (int) ($request->approval_level_count ?: 1)));

                    for ($level = 1; $level <= $levels; $level++) {
                        DB::table('approval_steps')->insertOrIgnore([
                            'organization_id' => $request->organization_id,
                            'leave_request_id' => $request->id,
                            'level' => $level,
                            'status' => 'pending',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }, 'leave_requests.id', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
