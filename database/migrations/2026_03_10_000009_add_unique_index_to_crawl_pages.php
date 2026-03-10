<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateIds = DB::table('crawl_pages as cp1')
            ->join('crawl_pages as cp2', function ($join) {
                $join->on('cp1.crawl_id', '=', 'cp2.crawl_id')
                    ->on('cp1.url', '=', 'cp2.url')
                    ->whereColumn('cp1.id', '>', 'cp2.id');
            })
            ->pluck('cp1.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('crawl_pages')->whereIn('id', $duplicateIds->all())->delete();
        }

        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->unique(['crawl_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropUnique('crawl_pages_crawl_id_url_unique');
        });
    }
};
