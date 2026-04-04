<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // How the user authenticated: email, google, phone, anonymous
            $table->string('auth_provider', 50)->nullable()->after('firebase_uid');
            // Top-level acquisition channel: utm_source value, 'referral', or 'direct'
            $table->string('signup_source', 100)->nullable()->after('auth_provider');
            // Full UTM context at time of sign-up
            $table->string('signup_utm_medium', 100)->nullable()->after('signup_source');
            $table->string('signup_utm_campaign', 100)->nullable()->after('signup_utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_provider', 'signup_source', 'signup_utm_medium', 'signup_utm_campaign']);
        });
    }
};
