<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id()->comment('CRM 고객 내부 ID');
            $table->string('uuid', 36)->unique()->comment('CRM 고객 공개 UUID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('연결된 코어 사용자 ID');
            $table->string('status', 20)->default('active')->comment('고객 상태');
            $table->string('name')->comment('고객 이름');
            $table->string('company')->nullable()->comment('회사명');
            $table->string('job_title')->nullable()->comment('직함');
            $table->string('email')->nullable()->comment('이메일 표시값');
            $table->string('phone', 50)->nullable()->comment('전화번호');
            $table->string('mobile', 50)->nullable()->comment('휴대전화번호');
            $table->string('locale', 10)->nullable()->comment('선호 로케일');
            $table->string('timezone', 50)->nullable()->comment('IANA 타임존');
            $table->string('source', 100)->nullable()->comment('유입 경로');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->comment('CRM 담당 사용자 ID');
            $table->timestamp('last_activity_at')->nullable()->comment('최근 CRM 활동 일시');
            $table->timestamp('next_action_at')->nullable()->comment('다음 조치 예정 일시');
            $table->json('metadata')->nullable()->comment('확장 메타데이터');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('last_activity_at');
            $table->index('next_action_at');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->comment('CRM 고객');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('crm_contacts')) {
            Schema::dropIfExists('crm_contacts');
        }
    }
};
