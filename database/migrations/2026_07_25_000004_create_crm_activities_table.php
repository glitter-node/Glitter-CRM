<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id()->comment('CRM 활동 내부 ID');
            $table->string('uuid', 36)->unique()->comment('CRM 활동 공개 UUID');
            $table->unsignedBigInteger('contact_id')->comment('CRM 고객 내부 ID');
            $table->string('type', 30)->comment('활동 유형');
            $table->string('subject')->nullable()->comment('활동 제목');
            $table->text('body')->nullable()->comment('활동 내용');
            $table->timestamp('occurred_at')->comment('활동 발생 일시(UTC)');
            $table->unsignedBigInteger('created_by')->nullable()->comment('기록한 코어 사용자 ID');
            $table->json('metadata')->nullable()->comment('확장 메타데이터');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_id', 'occurred_at']);
            $table->index('type');
            $table->index('occurred_at');
            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('crm_activities', function (Blueprint $table) {
                $table->comment('CRM 고객 활동');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
