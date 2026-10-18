<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->timestamp('next_task_at')
                ->nullable()
                ->index()
                ->comment('가장 이른 미완료 CRM 태스크 기한(UTC)');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('crm_contacts', 'next_task_at')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropIndex(['next_task_at']);
            $table->dropColumn('next_task_at');
        });
    }
};
