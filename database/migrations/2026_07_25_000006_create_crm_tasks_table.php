<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id()->comment('CRM 태스크 내부 ID');
            $table->string('uuid', 36)->unique()->comment('CRM 태스크 공개 UUID');
            $table->unsignedBigInteger('contact_id')->comment('CRM 고객 내부 ID');
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('담당 코어 사용자 ID');
            $table->string('title')->comment('태스크 제목');
            $table->text('description')->nullable()->comment('태스크 설명');
            $table->string('status', 20)
                ->default('pending')
                ->comment('태스크 상태');
            $table->string('priority', 20)
                ->default('normal')
                ->comment('태스크 우선순위');
            $table->timestamp('due_at')->nullable()->comment('태스크 기한(UTC)');
            $table->timestamp('completed_at')->nullable()->comment('완료 일시(UTC)');
            $table->json('metadata')->nullable()->comment('확장 메타데이터');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_id', 'status', 'due_at']);
            $table->index(['assigned_to', 'status', 'due_at']);
            $table->index('status');
            $table->index('priority');
            $table->index('due_at');
            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();
            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('crm_tasks', function (Blueprint $table) {
                $table->comment('CRM 고객 후속 태스크');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
