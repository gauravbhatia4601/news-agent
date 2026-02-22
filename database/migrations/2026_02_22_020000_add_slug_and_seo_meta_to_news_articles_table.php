<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('topic_id');
            $table->string('meta_title')->nullable()->after('title');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->text('meta_keywords')->nullable()->after('meta_description');
            $table->unique('slug');
        });

        $articles = DB::table('news_articles')
            ->select(['id', 'title'])
            ->orderBy('id')
            ->get();

        foreach ($articles as $article) {
            $base = Str::slug((string) $article->title);
            if ($base === '') {
                $base = 'article';
            }

            $slug = $base;
            $counter = 2;

            while (DB::table('news_articles')->where('slug', $slug)->where('id', '!=', $article->id)->exists()) {
                $slug = $base.'-'.$counter;
                $counter++;
            }

            DB::table('news_articles')
                ->where('id', $article->id)
                ->update(['slug' => $slug]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'meta_title', 'meta_description', 'meta_keywords']);
        });
    }
};
