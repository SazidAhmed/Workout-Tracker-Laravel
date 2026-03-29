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
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_number',
                'multiplier',
                'tax_exempt',
                'last_update',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('customer_number')->nullable()->after('password');
            $table->float('multiplier')->nullable()->after('address');
            $table->boolean('tax_exempt')->default(false)->after('multiplier');
            $table->dateTime('last_update')->nullable()->after('remember_token');
        });
    }
};
