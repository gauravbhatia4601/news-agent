<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI-provided benchmark scores from the OpenRouter feed
        // (benchmarks.artificial_analysis block). Nullable — ~30% coverage.
        Schema::table('ai_models', function (Blueprint $table) {
            $table->decimal('intelligence_index', 5, 1)->nullable();
            $table->decimal('coding_index', 5, 1)->nullable();
            $table->decimal('agentic_index', 5, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn(['intelligence_index', 'coding_index', 'agentic_index']);
        });
    }
};
