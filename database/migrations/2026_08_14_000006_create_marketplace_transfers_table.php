<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id');
            $table->string('stripe_transfer_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->string('transfer_group')->nullable();
            $table->string('status', 40)->default('pending');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('transfer_amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('MXN');
            $table->json('provider_payload')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'region_id']);
            $table->index(['status', 'created_at']);
            $table->index('stripe_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_transfers');
    }
};
