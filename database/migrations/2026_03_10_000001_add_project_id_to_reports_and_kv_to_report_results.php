<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'project_id')) {
                $table->uuid('project_id')->nullable()->after('analysis_id');
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                $table->index('project_id');
            }
        });

        Schema::table('report_results', function (Blueprint $table) {
            if (!Schema::hasColumn('report_results', 'key')) {
                $table->string('key')->nullable()->after('position');
                $table->text('value')->nullable()->after('key');
                $table->index(['report_id', 'key']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_results', function (Blueprint $table) {
            if (Schema::hasColumn('report_results', 'key')) {
                $table->dropIndex(['report_id', 'key']);
                $table->dropColumn(['key', 'value']);
            }
        });

        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropIndex(['project_id']);
                $table->dropColumn('project_id');
            }
        });
    }
};
