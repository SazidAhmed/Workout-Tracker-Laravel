<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default(UserRole::Client->value)->after('is_trashed');
            $table->foreignId('trainer_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->after('trainer_id')->constrained('users')->nullOnDelete();
            $table->string('invitation_token')->nullable()->after('invited_by_user_id');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invitation_expires_at');
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update(['role' => UserRole::Admin->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invited_by_user_id');
            $table->dropConstrainedForeignId('trainer_id');
            $table->dropColumn([
                'role',
                'invitation_token',
                'invitation_expires_at',
                'invitation_accepted_at',
            ]);
        });
    }
};
