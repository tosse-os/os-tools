<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('domain');
            $table->string('start_url');
            $table->string('status')->default('queued');
            $table->unsignedInteger('pages_scanned')->default(0);
            $table->unsignedInteger('pages_total')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawls');
    }
};
