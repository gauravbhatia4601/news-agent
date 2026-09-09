<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Full-text search (Option 1): weighted tsvector generated column + GIN index.
     * Postgres-only — sqlite (test suite) has no tsvector and keeps the ilike
     * fallback branch in the repository.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_strip_html(input text) RETURNS text AS $$
                SELECT regexp_replace(COALESCE(input, ''), '<[^>]*>', ' ', 'g')
            $$ LANGUAGE sql IMMUTABLE PARALLEL SAFE
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE news_articles ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(meta_description, '')), 'B') ||
                setweight(to_tsvector('english', immutable_strip_html(coalesce(content, ''))), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX news_articles_search_vector_idx ON news_articles USING gin(search_vector)');

        // Trigram bonus: substring + fuzzy tolerance on titles. Optional — if the
        // DB user cannot create extensions, full-text search still works.
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS news_articles_title_trgm_idx ON news_articles USING gin((title) gin_trgm_ops)');
        } catch (\Throwable $e) {
            Log::warning('pg_trgm extension unavailable, skipping trigram index.', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS news_articles_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS news_articles_search_vector_idx');
        DB::statement('ALTER TABLE news_articles DROP COLUMN IF EXISTS search_vector');
        DB::statement('DROP FUNCTION IF EXISTS immutable_strip_html(input text)');
    }
};
