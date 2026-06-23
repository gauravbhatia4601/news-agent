<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained('news_topics')->nullOnDelete();
            $table->string('provider', 60)->nullable();
            $table->string('model', 140)->nullable();
            $table->string('invocation_id', 100)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20)->default('success');
            $table->text('error')->nullable();
            $table->timestamp('invoked_at')->useCurrent();
            $table->index(['provider', 'model']);
            $table->index('invoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_invocations');
    }
};
