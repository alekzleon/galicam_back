<?php

namespace Tests\Feature;

use App\Models\Artisan;
use App\Models\Category;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicArtisansEndpointsTest extends TestCase
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_linea_id')->nullable();
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

    public function test_artisans_index_returns_active_artisans_with_region_and_product_count(): void
    {
        $region = Region::query()->create([
            'name' => 'Oaxaca',
            'slug' => 'oaxaca',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'TEXT',
            'name' => 'Textiles',
            'slug' => 'textiles',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Rebozo',
            'slug' => 'rebozo',
            'sku' => 'SKU-REBOZO',
            'default_price' => 500,
            'is_active' => true,
        ]);

        $artisan = Artisan::query()->create([
            'region_id' => $region->id,
            'name' => 'Juana Lopez',
            'slug' => 'juana-lopez',
            'history' => 'Tejedora tradicional',
            'contact' => 'juana@example.com',
            'photo_path' => 'artisans/juana.jpg',
            'translations' => [
                'name' => ['en' => 'Juana Lopez'],
                'history' => ['en' => 'Traditional weaver'],
                'contact' => ['en' => 'juana@example.com'],
                'photo_alt' => ['en' => 'Juana Lopez portrait'],
            ],
            'is_active' => true,
        ]);

        $artisan->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->getJson('/api/v1/artisans');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.slug', 'juana-lopez')
            ->assertJsonPath('data.0.region.slug', 'oaxaca')
            ->assertJsonPath('data.0.products_count', 1);

        $localizedResponse = $this->getJson('/api/v1/artisans?locale=en');

        $localizedResponse
            ->assertOk()
            ->assertJsonPath('data.0.history', 'Traditional weaver')
            ->assertJsonPath('data.0.photo_alt', 'Juana Lopez portrait');
    }

    public function test_artisan_show_returns_artisan_and_assigned_products(): void
    {
        $region = Region::query()->create([
            'name' => 'Chiapas',
            'slug' => 'chiapas',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'MASK',
            'name' => 'Masks',
            'slug' => 'masks',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Mascara ceremonial',
            'slug' => 'mascara-ceremonial',
            'sku' => 'SKU-MASCARA',
            'default_price' => 750,
            'stock' => 2,
            'is_active' => true,
        ]);

        $artisan = Artisan::query()->create([
            'region_id' => $region->id,
            'name' => 'Pedro Gomez',
            'slug' => 'pedro-gomez',
            'history' => 'Tallador de madera',
            'contact' => '555-123-1234',
            'is_active' => true,
        ]);

        $artisan->products()->attach($product->id, ['sort_order' => 1]);

        $response = $this->getJson('/api/v1/artisans/pedro-gomez');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.artisan.slug', 'pedro-gomez')
            ->assertJsonPath('data.artisan.region.slug', 'chiapas')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.slug', 'mascara-ceremonial');
    }

    public function test_random_spotlight_returns_active_artisan_photo_and_name_only(): void
    {
        $region = Region::query()->create([
            'name' => 'Yucatan',
            'slug' => 'yucatan',
            'is_active' => true,
        ]);

        Artisan::query()->create([
            'region_id' => $region->id,
            'name' => 'Artesano sin foto',
            'slug' => 'artesano-sin-foto',
            'is_active' => true,
        ]);

        Artisan::query()->create([
            'region_id' => $region->id,
            'name' => 'Rosa Maya',
            'slug' => 'rosa-maya',
            'photo_path' => 'artisans/rosa.jpg',
            'photo_alt' => 'Rosa Maya trabajando',
            'translations' => [
                'name' => ['en' => 'Rosa Maya'],
                'photo_alt' => ['en' => 'Rosa Maya working'],
            ],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/artisans/random/spotlight');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'photo_path',
                    'photo_url',
                    'photo_alt',
                ],
                'meta' => [
                    'locale',
                ],
            ])
            ->assertJsonMissingPath('data.slug')
            ->assertJsonMissingPath('data.history')
            ->assertJsonMissingPath('data.contact');
    }
}
