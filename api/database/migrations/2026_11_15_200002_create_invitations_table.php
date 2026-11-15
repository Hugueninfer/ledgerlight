<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('email');
            $table->string('role');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // One pending invitation per email per tenant; tenant_id leads,
            // so this index also serves the tenant_id foreign key.
            $table->unique(['tenant_id', 'email']);
            $table->tenantKey();

            // The inviter must be a user of the same tenant, enforced by MySQL.
            $table->tenantForeign('invited_by_user_id', 'users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
