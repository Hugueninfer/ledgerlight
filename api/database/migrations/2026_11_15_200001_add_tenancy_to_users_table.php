<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the default users table into a tenant-owned table. The default
 * migration is already on main (and immutable), so this alters it.
 * Assumes an empty users table: there is no backfill of tenant_id or role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained();
            $table->string('role')->after('email');
            $table->string('password')->nullable()->change();

            // Email is unique per tenant: sandboxes clone the same personas.
            // tenant_id leads this index, so it also serves the tenant_id foreign key.
            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email']);

            // Target of composite foreign keys from other tenant-owned tables.
            $table->tenantKey();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id', 'id']);
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropColumn(['tenant_id', 'role']);
            $table->unique('email');
            $table->string('password')->nullable(false)->change();
        });
    }
};
