<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('type');
            $table->string('url')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('alt_count')->default(0);
            $table->unsignedInteger('heading_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->index(['crawl_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_events');
    }
};
