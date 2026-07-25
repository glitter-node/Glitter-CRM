<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id()->comment('CRM 태그 내부 ID');
            $table->string('uuid', 36)->unique()->comment('CRM 태그 공개 UUID');
            $table->string('name')->comment('태그 표시 이름');
            $table->string('slug', 191)->unique()->comment('태그 고유 슬러그');
            $table->text('description')->nullable()->comment('태그 설명');
            $table->string('color', 7)->nullable()->comment('태그 색상 (#RRGGBB)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_tags');
    }
};
