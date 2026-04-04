<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'uuid')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
            $courses = DB::table('courses')->whereNull('uuid')->get(['id']);
            foreach ($courses as $c) {
                DB::table('courses')->where('id', $c->id)->update(['uuid' => (string) Str::uuid()]);
            }
        }

        if (! Schema::hasColumn('communities', 'uuid')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
            $communities = DB::table('communities')->whereNull('uuid')->get(['id']);
            foreach ($communities as $cm) {
                DB::table('communities')->where('id', $cm->id)->update(['uuid' => (string) Str::uuid()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'uuid')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }

        if (Schema::hasColumn('communities', 'uuid')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
