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
        Schema::table('group_sessions', function (Blueprint $table) {
            // Drop old columns if they exist (though we just created them in previous turn, better safe)
            if (Schema::hasColumn('group_sessions', 'price')) {
                $table->dropColumn('price');
            }

            // Rename/Change existing
            $table->renameColumn('room_name', 'livekit_room_name');

            // We use DB::statement for enum changes as Blueprint->change() is buggy for enums
            DB::statement("ALTER TABLE group_sessions MODIFY COLUMN session_type ENUM('open', 'couple', 'corporate', 'university') DEFAULT 'open'");
            DB::statement("ALTER TABLE group_sessions MODIFY COLUMN status ENUM('scheduled', 'live', 'completed', 'cancelled') DEFAULT 'scheduled'");

            // Add missing columns
            $table->enum('organiser_type', ['therapist', 'hr', 'manager', 'student_affairs'])->default('therapist')->after('status');
            $table->unsignedBigInteger('organiser_id')->nullable()->after('organiser_type');
            $table->foreignId('organization_id')->nullable()->after('therapist_id')->constrained('organizations')->onDelete('set null');
            $table->bigInteger('price_per_seat_kobo')->default(0)->after('max_participants');
            $table->boolean('is_recurring')->default(false)->after('duration_minutes');
            $table->string('recurrence_rule')->nullable()->after('is_recurring');
            $table->unsignedBigInteger('parent_session_id')->nullable()->after('recurrence_rule');
            $table->text('livekit_room_token')->nullable()->after('livekit_room_name');
            $table->string('language', 10)->default('en')->after('livekit_room_token');
            $table->json('topic_tags')->nullable()->after('language');
            $table->boolean('is_org_covered')->default(false)->after('topic_tags');
            $table->enum('payment_status', ['not_required', 'pending', 'paid'])->default('not_required')->after('is_org_covered');
            $table->boolean('reminder_24h_sent')->default(false);
            $table->boolean('reminder_1h_sent')->default(false);
        });

        Schema::table('group_session_participants', function (Blueprint $table) {
            // Make user_id nullable for guests
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Modify payment_status
            DB::statement("ALTER TABLE group_session_participants MODIFY COLUMN payment_status ENUM('not_required', 'pending', 'paid', 'failed', 'refunded') DEFAULT 'not_required'");

            // Add missing columns
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_name')->nullable()->after('guest_email');
            $table->string('invite_token', 64)->nullable()->unique()->after('guest_name');
            $table->enum('invite_status', ['pending', 'accepted', 'declined', 'attended', 'no_show'])->default('pending')->after('invite_token');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->enum('role_in_session', ['participant', 'host', 'observer'])->default('participant')->after('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse logic would go here if needed, but we're in an audit phase
    }
};
