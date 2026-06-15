<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_topics', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('generation_status');
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('thumbnail_url');
            $table->text('quality_report')->nullable()->after('status');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('news_topics', function (Blueprint $table) {
            $table->dropColumn('retry_count');
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'quality_report']);
        });
    }
};
