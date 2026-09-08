<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->unsignedInteger('views_1h')->default(0)->after('views');
            $table->unsignedInteger('views_6h')->default(0)->after('views_1h');
            $table->unsignedInteger('views_24h')->default(0)->after('views_6h');
            $table->double('momentum_score', 8, 4)->default(0)->after('views_24h');
            $table->timestamp('published_at')->nullable()->after('created_at');

            $table->index('momentum_score');
            $table->index(['status', 'published_at']);
        });

        // Backfill published_at for existing published articles.
        DB::table('news_articles')
            ->whereNull('published_at')
            ->where('status', 'published')
            ->update(['published_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropIndex('news_articles_momentum_score_index');
            $table->dropColumn(['views_1h', 'views_6h', 'views_24h', 'momentum_score', 'published_at']);
        });
    }
};
