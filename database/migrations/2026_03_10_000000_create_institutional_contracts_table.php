<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_user_id')->index();
            $table->string('company_name');
            $table->string('contract_type')->default('pilot');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('employee_count_limit')->nullable();
            $table->unsignedInteger('total_sessions_quota')->nullable();
            $table->unsignedInteger('sessions_used')->default(0);
            $table->json('features_enabled')->nullable();
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->string('status')->default('active');
            $table->string('document_url')->nullable();
            $table->timestamps();

            $table->foreign('institution_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_contracts');
    }
};
