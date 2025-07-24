<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('scans', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('url');
      $table->string('status')->default('pending');
      $table->integer('total')->default(0);
      $table->integer('current')->default(0);
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('scans');
  }
};
