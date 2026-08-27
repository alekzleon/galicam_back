<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryHomeEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('families');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('ecommerce_settings');

        Schema::create('ecommerce_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('module');
            $table->string('action');
            $table->string('summary');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_linea_id')->nullable();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('translations')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('grupo_linea_id')->nullable();
            $table->unsignedBigInteger('linea_articulo_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('translations')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('family_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('default_price', 12, 2)->default(0);
            $table->decimal('stock', 12, 2)->nullable();
            $table->string('short_description')->nullable();
            $table->string('brand')->nullable();
            $table->string('keyword')->nullable();
            $table->json('translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('processed')->default(false);
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->string('type')->default('percentage');
            $table->json('config')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_general')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('translations')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id');
            $table->foreignId('product_id');
            $table->timestamps();
        });

        Schema::create('product_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('families');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('ecommerce_settings');

        parent::tearDown();
    }

    public function test_categories_endpoint_returns_home_cards_with_images_and_links(): void
    {
        $category = Category::query()->create([
            'grupo_linea_id' => 120,
            'code' => 'TEXT',
            'name' => 'Textiles',
            'slug' => 'textiles',
            'image_path' => 'categories/textiles.jpg',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Tapete',
            'slug' => 'tapete',
            'sku' => 'SKU-TAPETE',
            'default_price' => 100,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Tapete inactivo',
            'slug' => 'tapete-inactivo',
            'sku' => 'SKU-TAPETE-OFF',
            'default_price' => 100,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.slug', 'textiles')
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonPath('data.0.links.products', url('/api/v1/categories/textiles/products'))
            ->assertJsonPath('data.0.links.catalog', url('/api/v1/products?category_slug=textiles'));
    }

    public function test_category_products_endpoint_returns_category_and_filtered_products(): void
    {
        $category = Category::query()->create([
            'grupo_linea_id' => 220,
            'code' => 'CERAM',
            'name' => 'Ceramics',
            'slug' => 'ceramics',
            'image_path' => 'categories/ceramics.jpg',
            'is_active' => true,
        ]);

        $otherCategory = Category::query()->create([
            'code' => 'MASK',
            'name' => 'Masks',
            'slug' => 'masks',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Florero',
            'slug' => 'florero',
            'sku' => 'SKU-FLORERO',
            'default_price' => 250,
            'stock' => 6,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $otherCategory->id,
            'name' => 'Mascara',
            'slug' => 'mascara',
            'sku' => 'SKU-MASCARA',
            'default_price' => 180,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/categories/ceramics/products');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.category.slug', 'ceramics')
            ->assertJsonPath('data.category.products_count', 1)
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.slug', 'florero');
    }
}
