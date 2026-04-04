<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('landing_page_content', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->string('key');
            $table->text('value')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section', 'key']);
            $table->index(['section', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('landing_page_content');
    }
};
