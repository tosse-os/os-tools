<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_id');
            $table->string('url')->nullable();
            $table->string('type');
            $table->string('severity');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('report_id')
                ->references('id')
                ->on('reports')
                ->onDelete('cascade');

            $table->index(['report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
