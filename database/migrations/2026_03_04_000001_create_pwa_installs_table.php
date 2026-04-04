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
        Schema::create('pwa_installs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('installed_at');
            $table->text('user_agent');
            $table->string('platform', 50);
            $table->string('language', 10);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('installed_at');
            $table->index('platform');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pwa_installs');
    }
};
