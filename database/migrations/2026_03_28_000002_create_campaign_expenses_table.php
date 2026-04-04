<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();

            // Budget linkage (optional — can be a standalone expense)
            $table->foreignId('department_budget_id')->nullable()->constrained()->nullOnDelete();

            // Platform / spend details
            $table->string('platform');            // facebook, instagram, twitter, google, etc.
            $table->string('description');
            $table->decimal('amount_planned', 15, 2);
            $table->decimal('amount_spent', 15, 2);
            $table->decimal('balance_remaining', 15, 2)->storedAs('amount_planned - amount_spent');
            $table->boolean('is_overspend')->storedAs('amount_spent > amount_planned');
            $table->decimal('overspend_amount', 15, 2)->storedAs(
                'CASE WHEN amount_spent > amount_planned THEN amount_spent - amount_planned ELSE 0 END'
            );
            $table->string('currency', 3)->default('NGN');
            $table->date('spend_date');

            // Proof of payment
            $table->string('proof_file_path')->nullable();   // stored in storage/app/private/...
            $table->string('proof_file_name')->nullable();
            $table->string('proof_file_type')->nullable();   // image/png, image/jpeg, application/pdf

            // Social media evidence (optional)
            $table->string('social_proof_url')->nullable();  // link to live post / campaign

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_expenses');
    }
};
