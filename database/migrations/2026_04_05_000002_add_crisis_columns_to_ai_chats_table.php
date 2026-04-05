<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->boolean('contains_crisis_keywords')->default(false)->after('risk_level');
            $table->boolean('requires_clinical_review')->default(false)->after('contains_crisis_keywords');
            $table->timestamp('reviewed_at')->nullable()->after('requires_clinical_review');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->dropColumn(['contains_crisis_keywords', 'requires_clinical_review', 'reviewed_at']);
        });
    }
};
