<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('crawl_pages', 'status_code')) {
                $table->integer('status_code')->nullable()->after('status');
            }

            if (!Schema::hasColumn('crawl_pages', 'title')) {
                $table->text('title')->nullable()->after('status_code');
            }

            if (!Schema::hasColumn('crawl_pages', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('title');
            }

            if (!Schema::hasColumn('crawl_pages', 'canonical')) {
                $table->text('canonical')->nullable()->after('meta_description');
            }

            if (!Schema::hasColumn('crawl_pages', 'h1_count')) {
                $table->integer('h1_count')->default(0)->after('heading_count');
            }

            if (!Schema::hasColumn('crawl_pages', 'image_count')) {
                $table->integer('image_count')->default(0)->after('h1_count');
            }

            if (!Schema::hasColumn('crawl_pages', 'alt_missing_count')) {
                $table->integer('alt_missing_count')->default(0)->after('image_count');
            }

            if (!Schema::hasColumn('crawl_pages', 'internal_links')) {
                $table->integer('internal_links')->default(0)->after('alt_missing_count');
            }

            if (!Schema::hasColumn('crawl_pages', 'external_links')) {
                $table->integer('external_links')->default(0)->after('internal_links');
            }

            if (!Schema::hasColumn('crawl_pages', 'text_hash')) {
                $table->string('text_hash')->nullable()->after('external_links');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn([
                'status_code',
                'title',
                'meta_description',
                'canonical',
                'h1_count',
                'image_count',
                'alt_missing_count',
                'internal_links',
                'external_links',
                'text_hash',
            ]);
        });
    }
};
