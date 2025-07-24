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
            // Account deletion fields
            $table->timestamp('deletion_requested_at')->nullable()->comment('When account deletion was requested');
            $table->text('deletion_reason')->nullable()->comment('User-provided reason for deletion');
            $table->timestamp('will_be_deleted_at')->nullable()->comment('Scheduled deletion date after grace period');
            $table->timestamp('deletion_failed_at')->nullable()->comment('When deletion process failed');
            $table->text('deletion_failure_reason')->nullable()->comment('Reason for deletion failure');

            // Indexes for deletion queries
            $table->index(['deletion_requested_at']);
            $table->index(['will_be_deleted_at']);
            $table->index(['is_active', 'deletion_requested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['deletion_requested_at']);
            $table->dropIndex(['will_be_deleted_at']);
            $table->dropIndex(['is_active', 'deletion_requested_at']);

            // Drop columns
            $table->dropColumn([
                'deletion_requested_at',
                'deletion_reason',
                'will_be_deleted_at',
                'deletion_failed_at',
                'deletion_failure_reason',
            ]);
        });
    }
}; 