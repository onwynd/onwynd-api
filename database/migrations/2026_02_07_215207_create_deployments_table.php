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
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->string('environment')->default('production');
            $table->string('status')->default('pending'); // pending, success, failed
            $table->unsignedBigInteger('deployed_by')->nullable();
            $table->string('duration')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('deployed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
