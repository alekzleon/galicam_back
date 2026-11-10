<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('paypal_order_id')->nullable()->unique()->after('stripe_payment_intent_id');
            $table->string('paypal_capture_id')->nullable()->unique()->after('paypal_order_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('paypal_order_id')->nullable()->after('stripe_payment_intent_id');
            $table->string('paypal_capture_id')->nullable()->after('paypal_order_id');

            $table->unique(['provider', 'paypal_order_id']);
            $table->unique(['provider', 'paypal_capture_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'paypal_capture_id']);
            $table->dropUnique(['provider', 'paypal_order_id']);
            $table->dropColumn(['paypal_order_id', 'paypal_capture_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paypal_order_id', 'paypal_capture_id']);
        });
    }
};
