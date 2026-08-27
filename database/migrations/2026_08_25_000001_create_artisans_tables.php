<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artisans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('history')->nullable();
            $table->text('contact')->nullable();
            $table->string('photo_disk')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('artisan_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['artisan_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artisan_product');
        Schema::dropIfExists('artisans');
    }
};
