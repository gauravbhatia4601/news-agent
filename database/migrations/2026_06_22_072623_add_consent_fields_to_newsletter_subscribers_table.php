<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('source');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->string('consent_text', 255)->nullable()->after('user_agent');
            $table->timestamp('consent_at')->nullable()->after('consent_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'consent_text', 'consent_at']);
        });
    }
};
