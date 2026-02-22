<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clustered topic labels can exceed 255 chars; store as TEXT.
        DB::statement('ALTER TABLE news_topics ALTER COLUMN topic_name TYPE TEXT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Truncate on rollback to avoid failure when existing rows exceed 255 chars.
        DB::statement('ALTER TABLE news_topics ALTER COLUMN topic_name TYPE VARCHAR(255) USING LEFT(topic_name, 255)');
    }
};

