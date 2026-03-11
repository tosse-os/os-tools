<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            if (!Schema::hasColumn('crawls', 'root_url')) {
                $table->string('root_url')->nullable()->after('domain');
            }

            if (!Schema::hasColumn('crawls', 'pages_discovered')) {
                $table->unsignedInteger('pages_discovered')->default(0)->after('status');
            }

            if (!Schema::hasColumn('crawls', 'pages_failed')) {
                $table->unsignedInteger('pages_failed')->default(0)->after('pages_scanned');
            }

            if (!Schema::hasColumn('crawls', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('pages_failed');
            }

            if (!Schema::hasColumn('crawls', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            $table->index(['status', 'created_at']);
        });

        Schema::table('crawl_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('crawl_pages', 'word_count')) {
                $table->unsignedInteger('word_count')->default(0)->after('external_links');
            }

            if (!Schema::hasColumn('crawl_pages', 'response_time')) {
                $table->unsignedInteger('response_time')->nullable()->after('text_hash');
            }

            if (!Schema::hasColumn('crawl_pages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            if (!Schema::hasColumn('crawl_pages', 'internal_links_count')) {
                $table->unsignedInteger('internal_links_count')->default(0)->after('alt_missing_count');
            }

            if (!Schema::hasColumn('crawl_pages', 'external_links_count')) {
                $table->unsignedInteger('external_links_count')->default(0)->after('internal_links_count');
            }
        });

        Schema::table('crawl_links', function (Blueprint $table) {
            if (!Schema::hasColumn('crawl_links', 'type')) {
                $table->string('type', 20)->default('internal')->after('target_url');
            }

            if (!Schema::hasColumn('crawl_links', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }

            $table->index('source_url');
            $table->index('target_url');
            $table->index(['crawl_id', 'status_code']);
        });

        Schema::create('crawl_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('type', 64);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->index(['crawl_id', 'type']);
            $table->index('created_at');
        });

        Schema::create('crawl_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('url');
            $table->unsignedInteger('depth')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->unique(['crawl_id', 'url']);
            $table->index(['crawl_id', 'status']);
        });

        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->index(['crawl_id', 'content_hash']);
            $table->index(['crawl_id', 'text_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropIndex(['crawl_id', 'content_hash']);
            $table->dropIndex(['crawl_id', 'text_hash']);
        });

        Schema::dropIfExists('crawl_queue');
        Schema::dropIfExists('crawl_events');
    }
};
