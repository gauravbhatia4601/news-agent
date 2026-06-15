<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_topics', function (Blueprint $table) {
            $table->foreignId('location_category_id')->nullable()->after('category_id')->constrained('categories')->nullOnDelete();
            $table->index('location_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('news_topics', function (Blueprint $table) {
            $table->dropIndex(['location_category_id']);
            $table->dropForeign(['location_category_id']);
            $table->dropColumn('location_category_id');
        });
    }
};
