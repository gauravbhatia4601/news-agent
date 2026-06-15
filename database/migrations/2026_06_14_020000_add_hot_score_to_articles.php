<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->float('hot_score')->default(0)->after('views');
            $table->float('view_velocity')->default(0)->after('hot_score');
            $table->index('hot_score');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex(['hot_score']);
            $table->dropColumn(['hot_score', 'view_velocity']);
        });
    }
};
