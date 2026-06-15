<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_topics', function (Blueprint $table) {
            $table->json('core_tokens')->nullable()->after('topic_signature');
        });

        $stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how', 'in', 'is', 'it', 'its',
            'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'why', 'with',
            'today', 'latest', 'live', 'update', 'updates', 'news',
        ];

        $topics = DB::table('news_topics')->whereNull('core_tokens')->get(['id', 'topic_name']);

        foreach ($topics as $topic) {
            $text = Str::of($topic->topic_name)
                ->lower()
                ->replaceMatches('/[^a-z0-9\s]/', ' ')
                ->squish()
                ->value();

            $tokens = array_values(array_unique(array_filter(
                explode(' ', $text),
                fn ($t) => $t !== '' && ! in_array($t, $stopWords, true) && strlen($t) > 2
            )));

            DB::table('news_topics')->where('id', $topic->id)->update([
                'core_tokens' => json_encode($tokens),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('news_topics', function (Blueprint $table) {
            $table->dropColumn('core_tokens');
        });
    }
};
