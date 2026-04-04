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
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_id')->constrained('ambassadors')->onDelete('cascade');
            $table->string('code', 20)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->integer('uses_count')->default(0);
            $table->integer('max_uses')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('ambassador_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
