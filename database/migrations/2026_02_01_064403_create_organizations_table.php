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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'corporate', 'university'
            $table->string('domain')->nullable(); // For email verification e.g. 'company.com'
            $table->json('sso_config')->nullable();
            $table->string('contact_email');
            $table->string('status')->default('active'); // active, suspended, pending
            $table->string('subscription_plan')->default('basic');
            $table->integer('max_members')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
