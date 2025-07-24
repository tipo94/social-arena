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
        Schema::table('users', function (Blueprint $table) {
            // Check if columns exist before adding them to avoid duplicates
            
            // Additional personal info (only add if not exists)
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
            
            // Social login (these are definitely new)
            $table->string('social_provider')->nullable()->after('remember_token');
            $table->string('social_provider_id')->nullable()->after('social_provider');
            $table->string('social_avatar_url')->nullable()->after('social_provider_id');
            
            // Account type and subscription
            $table->enum('account_type', ['free', 'premium', 'business'])->default('free')->after('social_avatar_url');
            $table->enum('subscription_status', ['inactive', 'active', 'cancelled', 'expired'])->default('inactive')->after('account_type');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
            
            // Add soft deletes if not exists
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
            
            // Indexes for performance (only add new ones)
            $table->index(['social_provider', 'social_provider_id']); // Social login
            $table->index(['subscription_status', 'subscription_expires_at']); // Premium users
            if (Schema::hasColumn('users', 'username')) {
                $table->index(['username']); // Username lookups
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['subscription_status', 'subscription_expires_at']);
            $table->dropIndex(['social_provider', 'social_provider_id']);
            if (Schema::hasColumn('users', 'username')) {
                $table->dropIndex(['username']);
            }
            
            // Drop soft deletes if exists
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            
            // Drop only the new columns we added
            $table->dropColumn([
                'social_provider',
                'social_provider_id',
                'social_avatar_url',
                'account_type',
                'subscription_status',
                'subscription_expires_at',
            ]);
            
            // Drop optional columns only if they exist and we added them
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn(['username']);
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn(['first_name']);
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn(['last_name']);
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn(['phone']);
            }
            if (Schema::hasColumn('users', 'phone_verified_at')) {
                $table->dropColumn(['phone_verified_at']);
            }
        });
    }
};
