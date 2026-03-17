<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->string('canonical_url')->nullable()->after('url');
            $table->string('content_hash', 64)->nullable()->after('error');
            $table->unsignedInteger('internal_links_in')->default(0)->after('content_hash');
            $table->unsignedInteger('internal_links_out')->default(0)->after('internal_links_in');
            $table->unsignedInteger('depth')->default(0)->after('internal_links_out');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn([
                'canonical_url',
                'content_hash',
                'internal_links_in',
                'internal_links_out',
                'depth',
            ]);
        });
    }
};
