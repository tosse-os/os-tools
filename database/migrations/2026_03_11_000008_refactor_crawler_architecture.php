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
                $table->unsignedBigInteger('pages_discovered')->default(0)->after('status');
            }
            if (!Schema::hasColumn('crawls', 'pages_failed')) {
                $table->unsignedBigInteger('pages_failed')->default(0)->after('pages_scanned');
            }
            if (!Schema::hasColumn('crawls', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('pages_failed');
            }
            if (!Schema::hasColumn('crawls', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('crawl_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('crawl_pages', 'status_code')) {
                $table->unsignedSmallInteger('status_code')->nullable()->after('url');
            }
            if (!Schema::hasColumn('crawl_pages', 'depth')) {
                $table->unsignedInteger('depth')->default(0)->after('status_code');
            }
            if (!Schema::hasColumn('crawl_pages', 'title')) {
                $table->string('title')->nullable()->after('depth');
            }
            if (!Schema::hasColumn('crawl_pages', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('crawl_pages', 'h1_count')) {
                $table->unsignedInteger('h1_count')->default(0)->after('meta_description');
            }
            if (!Schema::hasColumn('crawl_pages', 'alt_missing_count')) {
                $table->unsignedInteger('alt_missing_count')->default(0)->after('h1_count');
            }
            if (!Schema::hasColumn('crawl_pages', 'internal_links_count')) {
                $table->unsignedInteger('internal_links_count')->default(0)->after('alt_missing_count');
            }
            if (!Schema::hasColumn('crawl_pages', 'external_links_count')) {
                $table->unsignedInteger('external_links_count')->default(0)->after('internal_links_count');
            }
            if (!Schema::hasColumn('crawl_pages', 'word_count')) {
                $table->unsignedInteger('word_count')->default(0)->after('external_links_count');
            }
            if (!Schema::hasColumn('crawl_pages', 'content_hash')) {
                $table->string('content_hash', 64)->nullable()->after('word_count');
            }
            if (!Schema::hasColumn('crawl_pages', 'text_hash')) {
                $table->string('text_hash', 64)->nullable()->after('content_hash');
            }
            if (!Schema::hasColumn('crawl_pages', 'response_time')) {
                $table->unsignedInteger('response_time')->nullable()->after('text_hash');
            }
            if (!Schema::hasColumn('crawl_pages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->unique(['crawl_id', 'url'], 'crawl_pages_crawl_id_url_unique');
            $table->index(['crawl_id', 'content_hash'], 'crawl_pages_crawl_id_content_hash_index');
            $table->index(['crawl_id', 'text_hash'], 'crawl_pages_crawl_id_text_hash_index');
        });

        Schema::create('crawl_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('source_url');
            $table->string('target_url');
            $table->string('type', 16)->default('internal');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('redirect_chain_length')->default(0);
            $table->timestamps();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->index('crawl_id');
            $table->index('source_url');
            $table->index('target_url');
            $table->unique(['crawl_id', 'source_url', 'target_url', 'type'], 'crawl_links_unique_edge');
        });

        Schema::create('crawl_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('type', 64);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->index(['crawl_id', 'type']);
        });

        Schema::create('crawl_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('crawl_id');
            $table->string('url');
            $table->unsignedInteger('depth')->default(0);
            $table->string('status', 32)->default('queued');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('crawl_id')->references('id')->on('crawls')->onDelete('cascade');
            $table->unique(['crawl_id', 'url'], 'crawl_queue_crawl_id_url_unique');
            $table->index(['crawl_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_queue');
        Schema::dropIfExists('crawl_events');
        Schema::dropIfExists('crawl_links');

        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropUnique('crawl_pages_crawl_id_url_unique');
            $table->dropIndex('crawl_pages_crawl_id_content_hash_index');
            $table->dropIndex('crawl_pages_crawl_id_text_hash_index');
        });
    }
};
