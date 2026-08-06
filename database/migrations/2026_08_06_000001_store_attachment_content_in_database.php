<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('request_attachments', 'file_content')) {
            Schema::table('request_attachments', function (Blueprint $table): void {
                $table->binary('file_content')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('request_attachments', 'file_content')) {
            Schema::table('request_attachments', function (Blueprint $table): void {
                $table->dropColumn('file_content');
            });
        }
    }
};
