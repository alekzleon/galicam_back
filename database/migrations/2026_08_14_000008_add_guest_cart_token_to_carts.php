<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('guest_cart_token', 80)->nullable()->after('user_id')->unique();
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('cart_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['guest_cart_token']);
            $table->dropColumn('guest_cart_token');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
