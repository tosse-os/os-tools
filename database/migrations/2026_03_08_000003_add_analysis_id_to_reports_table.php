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
        Schema::table('reports', function (Blueprint $table) {
            $table->uuid('analysis_id')->nullable()->after('user_id');
            $table->foreign('analysis_id')->references('id')->on('analyses')->nullOnDelete();
            $table->index('analysis_id');
        });

        $reportGroups = DB::table('reports')
            ->select('user_id', 'url', 'keyword', 'city')
            ->groupBy('user_id', 'url', 'keyword', 'city')
            ->get();

        foreach ($reportGroups as $group) {
            $domain = parse_url((string) $group->url, PHP_URL_HOST) ?: (string) $group->url;
            $projectName = $domain !== '' ? $domain : 'Unassigned Project';

            $project = DB::table('projects')
                ->where('user_id', $group->user_id)
                ->where('domain', $domain)
                ->first();

            if (!$project) {
                $projectId = (string) Str::uuid();
                DB::table('projects')->insert([
                    'id' => $projectId,
                    'user_id' => $group->user_id,
                    'name' => $projectName,
                    'domain' => $domain,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $projectId = $project->id;
            }

            $analysisId = (string) Str::uuid();
            DB::table('analyses')->insert([
                'id' => $analysisId,
                'project_id' => $projectId,
                'keyword' => $group->keyword,
                'city' => $group->city,
                'url' => $group->url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('reports')
                ->where('user_id', $group->user_id)
                ->where('url', $group->url)
                ->where(function ($query) use ($group) {
                    if ($group->keyword === null) {
                        $query->whereNull('keyword');
                    } else {
                        $query->where('keyword', $group->keyword);
                    }
                })
                ->where(function ($query) use ($group) {
                    if ($group->city === null) {
                        $query->whereNull('city');
                    } else {
                        $query->where('city', $group->city);
                    }
                })
                ->update(['analysis_id' => $analysisId]);
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['analysis_id']);
            $table->dropIndex(['analysis_id']);
            $table->dropColumn('analysis_id');
        });
    }
};
