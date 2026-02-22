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
        Schema::create('news_topics', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60)->index();
            $table->string('topic_name');
            $table->string('topic_signature', 64)->unique();
            $table->unsignedInteger('source_count')->default(0);
            $table->string('generation_status', 25)->default('pending');
            $table->timestamp('llm_generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('news_topic_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('news_topics')->cascadeOnDelete();
            $table->string('source_name', 120);
            $table->text('source_url');
            $table->string('source_url_hash', 64);
            $table->text('headline');
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'source_url_hash'], 'topic_source_url_hash_unique');
            $table->index(['source_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_topic_sources');
        Schema::dropIfExists('news_topics');
    }
};
