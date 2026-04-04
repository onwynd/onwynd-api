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
        Schema::create('organization_session_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('session_id');
            $table->decimal('amount_covered', 10, 2)->default(0);
            $table->decimal('amount_charged_to_user', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('organization_members')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('therapy_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_session_logs');
    }
};
