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
        Schema::table('organizations', function (Blueprint $table) {
            // University configuration (applies to type = 'university')
            $table->enum('funding_model', ['model_a', 'model_b'])->nullable()->after('type');
            $table->enum('billing_cycle', ['monthly', 'semester', 'annual'])->nullable()->after('funding_model');
            $table->unsignedTinyInteger('semester_start_month')->nullable()->after('billing_cycle');
            $table->unsignedTinyInteger('semester_2_start_month')->nullable()->after('semester_start_month');
            $table->unsignedInteger('session_credits_per_student')->default(3)->after('max_members');
            $table->unsignedInteger('session_ceiling_ngn')->default(15000)->after('session_credits_per_student');
            $table->boolean('domain_auto_join')->default(false)->after('session_ceiling_ngn');
            $table->string('university_domain')->nullable()->after('domain_auto_join');
            $table->boolean('student_id_verification')->default(false)->after('university_domain');
            $table->string('crisis_notification_email')->nullable()->after('student_id_verification');
            $table->boolean('early_crisis_detection')->default(true)->after('crisis_notification_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'funding_model',
                'billing_cycle',
                'semester_start_month',
                'semester_2_start_month',
                'session_credits_per_student',
                'session_ceiling_ngn',
                'domain_auto_join',
                'university_domain',
                'student_id_verification',
                'crisis_notification_email',
                'early_crisis_detection',
            ]);
        });
    }
};
