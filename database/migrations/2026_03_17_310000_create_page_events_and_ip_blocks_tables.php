<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Web analytics: one row per page view / event
        if (Schema::hasTable('page_events')) {
            if (!$this->indexExists('page_events', 'pe_page_created_idx')) {
                Schema::table('page_events', function (Blueprint $table) {
                    $table->index([DB::raw('page(512)'), 'created_at'], 'pe_page_created_idx');
                });
            }

            if (!$this->indexExists('page_events', 'pe_ip_created_idx')) {
                Schema::table('page_events', function (Blueprint $table) {
                    $table->index(['ip', 'created_at'], 'pe_ip_created_idx');
                });
            }

            if (!$this->indexExists('page_events', 'pe_country_created_idx')) {
                Schema::table('page_events', function (Blueprint $table) {
                    $table->index(['country', 'created_at'], 'pe_country_created_idx');
                });
            }
        } else {
        Schema::create('page_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->index();       // anonymous session token
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('page', 2048);                    // /dashboard, /booking, etc.
            $table->string('referrer', 2048)->nullable();
            $table->string('utm_source', 191)->nullable();
            $table->string('utm_medium', 191)->nullable();
            $table->string('utm_campaign', 191)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedSmallInteger('scroll_pct')->default(0); // max scroll % reached (0–100)
            $table->unsignedInteger('duration_ms')->default(0);     // time on page in ms
            $table->enum('visitor_type', ['new', 'returning'])->default('new');
            $table->enum('quality', ['human', 'bot', 'suspicious'])->default('human');
            $table->string('event_type', 50)->default('pageview'); // pageview | click | scroll
            $table->json('meta')->nullable();                // extra payload (link_href, element, etc.)
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index([DB::raw('page(512)'), 'created_at'], 'pe_page_created_idx');
            $table->index(['ip', 'created_at'], 'pe_ip_created_idx');
            $table->index(['country', 'created_at'], 'pe_country_created_idx');
        });
        }

        // IP block list — admins add entries, middleware enforces them
        if (!Schema::hasTable('ip_blocks')) {
            Schema::create('ip_blocks', function (Blueprint $table) {
                $table->id();
                $table->string('ip_or_cidr', 50)->unique();     // single IP or CIDR range e.g. 192.168.1.0/24
                $table->string('reason', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at')->nullable();    // null = permanent
                $table->timestamps();

                $table->index(['is_active', 'ip_or_cidr']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_events');
        Schema::dropIfExists('ip_blocks');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->limit(1)
            ->exists();
    }
};
