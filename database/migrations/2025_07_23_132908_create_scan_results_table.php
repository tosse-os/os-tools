<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScanResultsTable extends Migration
{
    public function up(): void
    {
        Schema::create('scan_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('scan_id');
            $table->string('url');
            $table->integer('status_code')->nullable();
            $table->string('title')->nullable();
            $table->integer('alt_missing')->nullable();
            $table->integer('alt_empty')->nullable();
            $table->integer('heading_count')->nullable();
            $table->text('heading_issues')->nullable();
            $table->text('errors')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->foreign('scan_id')->references('id')->on('scans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_results');
    }
}
