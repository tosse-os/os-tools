<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_id');
            $table->string('module');
            $table->string('url')->nullable();
            $table->integer('position')->nullable();
            $table->integer('score')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('report_id')
                ->references('id')
                ->on('reports')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_results');
    }
};
