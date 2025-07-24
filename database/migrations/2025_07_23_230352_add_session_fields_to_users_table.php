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
            // Session and activity tracking
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('last_activity_at')->nullable()->after('last_login_ip');
            $table->boolean('is_online')->default(false)->after('last_activity_at');
            
            // Account status and verification
            $table->boolean('is_active')->default(true)->after('is_online');
            $table->boolean('is_banned')->default(false)->after('is_active');
            $table->timestamp('banned_until')->nullable()->after('is_banned');
            $table->text('ban_reason')->nullable()->after('banned_until');
            
            // Social networking features
            $table->enum('role', ['user', 'moderator', 'admin', 'super_admin'])->default('user')->after('ban_reason');
            $table->json('permissions')->nullable()->after('role'); // Additional granular permissions
            
            // User preferences
            $table->string('timezone', 50)->default('UTC')->after('permissions');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->enum('theme', ['light', 'dark', 'auto'])->default('light')->after('locale');
            
            // Two-factor authentication
            $table->boolean('two_factor_enabled')->default(false)->after('theme');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            
            // Account metrics (denormalized for performance)
            $table->unsignedInteger('login_count')->default(0)->after('two_factor_recovery_codes');
            $table->date('last_password_change')->nullable()->after('login_count');
            
            // Soft deletes
            $table->softDeletes()->after('last_password_change');
            
            // Additional indexes for performance
            $table->index(['is_active', 'is_banned']); // Active users
            $table->index(['last_activity_at', 'is_online']); // Online users
            $table->index(['role', 'is_active']); // Users by role
            $table->index(['last_login_at', 'is_active']); // Recent logins
            $table->index(['is_online', 'last_activity_at']); // Online status tracking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_login_at',
                'last_login_ip',
                'last_activity_at',
                'is_online',
                'is_active',
                'is_banned',
                'banned_until',
                'ban_reason',
                'role',
                'permissions',
                'timezone',
                'locale',
                'theme',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'login_count',
                'last_password_change'
            ]);
            
            // Drop the indexes
            $table->dropIndex(['is_active', 'is_banned']);
            $table->dropIndex(['last_activity_at', 'is_online']);
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['last_login_at', 'is_active']);
            $table->dropIndex(['is_online', 'last_activity_at']);
        });
    }
};
