<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('connection')->default('default');
            $table->string('queue')->default('default');
            $table->string('job_uuid', 64)->nullable()->index();
            $table->string('job_class', 255)->nullable()->index();
            $table->string('job_signature', 255)->nullable()->index();
            $table->string('status', 32)->index(); // pending, processing, processed, failed, released
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('exception')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['job_uuid', 'status']);
            $table->unique(['connection', 'queue', 'job_uuid'], 'queue_job_logs_connection_queue_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_job_logs');
    }
};
