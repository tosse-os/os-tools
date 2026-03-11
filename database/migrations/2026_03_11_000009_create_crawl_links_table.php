<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('source_url');
            $table->string('target_url');
            $table->string('link_type', 20);
            $table->text('anchor_text')->nullable();
            $table->boolean('nofollow')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('redirect_target')->nullable();
            $table->unsignedSmallInteger('redirect_chain_length')->default(0);
            $table->json('redirect_chain')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->index('crawl_id');
            $table->index('status_code');
            $table->index('link_type');
            $table->index(['crawl_id', 'source_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_links');
    }
};
