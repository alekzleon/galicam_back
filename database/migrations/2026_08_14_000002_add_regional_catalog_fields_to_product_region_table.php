<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_region', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('product_id');
            $table->decimal('regional_price', 12, 2)->nullable()->after('is_active');
            $table->decimal('regional_stock', 12, 2)->nullable()->after('regional_price');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('regional_stock');
            $table->json('metadata')->nullable()->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('product_region', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'regional_price',
                'regional_stock',
                'commission_rate',
                'metadata',
            ]);
        });
    }
};
