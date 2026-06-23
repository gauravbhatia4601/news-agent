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
        $connection = DB::connection()->getDriverName();

        $exists = false;
        if ($connection === 'pgsql') {
            $exists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'queue_job_logs' AND indexname = 'queue_job_logs_connection_queue_uuid_unique'"))->isNotEmpty();
        } else {
            $indexes = DB::select("PRAGMA index_list('queue_job_logs')");
            $exists = collect($indexes)->contains(fn ($i) => $i->name === 'queue_job_logs_connection_queue_uuid_unique');
        }

        if (! $exists) {
            Schema::table('queue_job_logs', function (Blueprint $table) {
                $table->unique(['connection', 'queue', 'job_uuid'], 'queue_job_logs_connection_queue_uuid_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_job_logs', function (Blueprint $table) {
            $table->dropUnique('queue_job_logs_connection_queue_uuid_unique');
        });
    }
};
