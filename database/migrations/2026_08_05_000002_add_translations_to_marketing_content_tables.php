<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['banners', 'brand_banners', 'monthly_promotions', 'promotions', 'contact_faqs', 'gift_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('translations')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['banners', 'brand_banners', 'monthly_promotions', 'promotions', 'contact_faqs', 'gift_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
    }
};
