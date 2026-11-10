<?php

namespace Tests\Feature;

use App\Models\Artisan;
use App\Models\Category;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeFeaturedArtisanProductsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('artisan_product');
        Schema::dropIfExists('artisans');
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('families');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('regions');
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

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('translations')->nullable();
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
            $table->string('slug')->nullable()->unique();
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

        Schema::create('artisans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('history')->nullable();
            $table->text('contact')->nullable();
            $table->string('photo_disk')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_alt')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('artisan_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id');
            $table->foreignId('product_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('artisan_product');
        Schema::dropIfExists('artisans');
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('families');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('ecommerce_settings');

        parent::tearDown();
    }

    public function test_featured_artisan_products_endpoint_returns_section_contract(): void
    {
        $region = Region::query()->create([
            'name' => 'Mexico',
            'slug' => 'mexico',
        ]);

        $category = Category::query()->create([
            'code' => 'TEXT',
            'name' => 'Textiles',
            'slug' => 'textiles',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Huipil de Chiapas',
            'slug' => 'huipil-de-chiapas',
            'sku' => 'SKU-HUIPIL',
            'default_price' => 195,
            'stock' => 6,
            'image_path' => 'products/huipil.jpg',
            'is_active' => true,
            'translations' => [
                'name' => ['en' => 'Chiapas Huipil'],
            ],
        ]);

        $artisan = Artisan::query()->create([
            'region_id' => $region->id,
            'name' => 'Juana Gomez',
            'slug' => 'juana-gomez',
            'translations' => [
                'name' => ['en' => 'Juana Gomez'],
            ],
            'is_active' => true,
        ]);

        $artisan->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->getJson('/api/v1/home/featured-artisan-products?locale=es');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.eyebrow', 'Arte destacado')
            ->assertJsonPath('data.title', 'Piezas seleccionadas')
            ->assertJsonPath('data.view_all.url', url('/api/v1/products'))
            ->assertJsonPath('data.items.0.title', 'Chiapas Huipil')
            ->assertJsonPath('data.items.0.artisan.name', 'Juana Gomez')
            ->assertJsonPath('data.items.0.region.name', 'Mexico')
            ->assertJsonPath('data.items.0.price', 195)
            ->assertJsonPath('data.items.0.links.product_detail', url('/api/v1/products/huipil-de-chiapas'));
    }
}
