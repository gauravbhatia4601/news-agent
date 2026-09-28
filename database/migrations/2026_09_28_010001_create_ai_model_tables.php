<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_provider_id')->constrained()->cascadeOnDelete();
            // Full feed id including variant suffix, e.g. anthropic/claude-sonnet-5:batch.
            $table->string('feed_id', 255)->unique();
            // Which feed this row came from (openrouter today; huggingface later).
            $table->string('source', 60)->default('openrouter');
            $table->string('name', 255);
            $table->unsignedBigInteger('context_length')->nullable();
            // USD per million tokens, converted at sync time from per-token strings.
            $table->decimal('input_price_per_million', 12, 6)->nullable();
            $table->decimal('output_price_per_million', 12, 6)->nullable();
            $table->string('modality', 120)->nullable();
            // base | batch | free
            $table->string('variant', 20)->default('base');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('source', 60)->unique();
            $table->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sync_state');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_model_providers');
    }
};
