<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('metadata');
            $table->string('stripe_connect_status')->default('not_started')->after('stripe_account_id');
            $table->boolean('stripe_details_submitted')->default(false)->after('stripe_connect_status');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_details_submitted');
            $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
            $table->json('stripe_capabilities')->nullable()->after('stripe_payouts_enabled');
            $table->json('stripe_requirements')->nullable()->after('stripe_capabilities');
            $table->timestamp('stripe_synced_at')->nullable()->after('stripe_requirements');

            $table->index('stripe_account_id');
            $table->index('stripe_connect_status');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropIndex(['stripe_account_id']);
            $table->dropIndex(['stripe_connect_status']);
            $table->dropColumn([
                'stripe_account_id',
                'stripe_connect_status',
                'stripe_details_submitted',
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'stripe_capabilities',
                'stripe_requirements',
                'stripe_synced_at',
            ]);
        });
    }
};
