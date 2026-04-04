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
        // Update Badges table
        if (Schema::hasTable('badges') && ! Schema::hasColumn('badges', 'slug')) {
            Schema::table('badges', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });
        }

        // Streaks
        if (! Schema::hasTable('streaks')) {
            Schema::create('streaks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->integer('current_streak')->default(0);
                $table->integer('longest_streak')->default(0);
                $table->date('last_activity_date')->nullable();
                $table->timestamps();
            });
        }

        // Challenges
        if (! Schema::hasTable('challenges')) {
            Schema::create('challenges', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description');
                $table->string('type'); // anxiety_free, community_support, meditation, booking
                $table->integer('goal_count');
                $table->string('reward_type')->nullable(); // badge, credit
                $table->string('reward_value')->nullable();
                $table->dateTime('start_date');
                $table->dateTime('end_date');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // User Challenge Progress
        if (! Schema::hasTable('user_challenge_progress')) {
            Schema::create('user_challenge_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('challenge_id')->constrained('challenges')->onDelete('cascade');
                $table->integer('current_progress')->default(0);
                $table->boolean('is_completed')->default(false);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'challenge_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challenge_progress');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('streaks');
        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
