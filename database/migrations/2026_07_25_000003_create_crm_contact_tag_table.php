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
        Schema::create('crm_contact_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->comment('CRM 고객 내부 ID');
            $table->unsignedBigInteger('tag_id')->comment('CRM 태그 내부 ID');
            $table->timestamps();

            $table->unique(['contact_id', 'tag_id'], 'crm_contact_tag_contact_tag_unique');
            $table->index('tag_id', 'crm_contact_tag_tag_index');
            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();
            $table->foreign('tag_id')
                ->references('id')
                ->on('crm_tags')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contact_tag');
    }
};
