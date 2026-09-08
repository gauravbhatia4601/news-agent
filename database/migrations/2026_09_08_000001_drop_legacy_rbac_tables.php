<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the legacy RBAC tables (roles, permissions, role_user, role_permission).
 *
 * The Role/Permission Eloquent models were removed from app/ and no code
 * references these tables anymore. Pivot tables are dropped first to satisfy
 * FK constraints on sqlite (enforces FKs when PRAGMA foreign_keys=ON) and
 * Postgres (always enforces). Uses dropIfExists so this is a no-op on
 * fresh installs where the tables were never created.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FK-safe order: pivots first, then parents.
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    public function down(): void
    {
        // No-op: these tables are dead. Recating them would require the
        // original create-migrations which still exist earlier in the chain.
        // Intentionally left empty — rollback does not resurrect legacy schema.
    }
};