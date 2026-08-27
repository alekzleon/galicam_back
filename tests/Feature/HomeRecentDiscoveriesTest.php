<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeRecentDiscoveriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
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
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('image_path')->nullable();
            $table->decimal('default_price', 12, 2)->default(0);
            $table->decimal('stock', 12, 2)->nullable();
            $table->json('translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('ecommerce_settings');

        parent::tearDown();
    }

    public function test_recent_discoveries_endpoint_returns_six_random_products_section_contract(): void
    {
        $category = Category::query()->create([
            'code' => 'HOME',
            'name' => 'Home',
            'slug' => 'home',
        ]);

        foreach (range(1, 6) as $index) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => "Producto {$index}",
                'slug' => "producto-{$index}",
                'sku' => "SKU-{$index}",
                'image_path' => "products/producto-{$index}.jpg",
                'default_price' => 100 + $index,
                'stock' => 5 + $index,
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/v1/home/recent-discoveries');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.eyebrow', 'Nuevo y destacado')
            ->assertJsonPath('data.title', 'Descubrimientos recientes')
            ->assertJsonPath('data.view_all.url', url('/api/v1/products'))
            ->assertJsonCount(6, 'data.items')
            ->assertJsonStructure([
                'data' => [
                    'eyebrow',
                    'title',
                    'view_all' => ['label', 'url'],
                    'items' => [
                        '*' => [
                            'id',
                            'title',
                            'slug',
                            'image_url',
                            'price',
                            'price_money',
                            'stock',
                            'stock_label',
                            'links' => ['product_detail'],
                        ],
                    ],
                ],
            ]);
    }
}
