<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_reward_configs', function (Blueprint $table) {
            $table->id();
            // 'referee' = reward given to the newly signed-up user who used any referral code
            $table->enum('referrer_tier', ['freemium', 'paid', 'referee']);
            $table->enum('reward_type', ['ai_quota', 'discount_percent']);
            $table->decimal('reward_value', 8, 2)->default(0);
            // signup = reward immediately on sign-up; first_subscription = reward when referee pays
            $table->enum('reward_trigger', ['signup', 'first_subscription']);
            $table->boolean('is_enabled')->default(true);
            // Max cumulative discount a paid referrer can earn (anti-abuse cap)
            $table->decimal('max_discount_cap', 5, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique('referrer_tier'); // one config row per tier
        });

        // Seed defaults
        DB::table('referral_reward_configs')->insert([
            [
                'referrer_tier'    => 'freemium',
                'reward_type'      => 'ai_quota',
                'reward_value'     => 5,
                'reward_trigger'   => 'signup',
                'is_enabled'       => true,
                'max_discount_cap' => null,
                'notes'            => 'Freemium referrer gets +5 AI companion chats per successful sign-up',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'referrer_tier'    => 'paid',
                'reward_type'      => 'discount_percent',
                'reward_value'     => 5,
                'reward_trigger'   => 'first_subscription',
                'is_enabled'       => true,
                'max_discount_cap' => 50,
                'notes'            => 'Paid referrer earns 5% discount per paying conversion (capped at 50%)',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'referrer_tier'    => 'referee',
                'reward_type'      => 'ai_quota',
                'reward_value'     => 10,
                'reward_trigger'   => 'signup',
                'is_enabled'       => true,
                'max_discount_cap' => null,
                'notes'            => 'Any referred user gets +10 AI companion chats on sign-up',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_reward_configs');
    }
};
