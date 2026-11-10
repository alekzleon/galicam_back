<?php

namespace Tests\Feature;

use App\Models\Region;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeFeaturedRegionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('product_region');
        Schema::dropIfExists('products');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('ecommerce_settings');

        Schema::create('ecommerce_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('banner_disk')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('banner_alt')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_region', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id');
            $table->foreignId('product_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('product_region');
        Schema::dropIfExists('products');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('ecommerce_settings');

        parent::tearDown();
    }

    public function test_featured_regions_endpoint_returns_six_random_regions_with_image_and_copy(): void
    {
        Region::query()->create([
            'name' => 'Mexico',
            'slug' => 'mexico',
            'description' => 'Tradiciones ricas y artesania vibrante.',
            'banner_disk' => 'public',
            'banner_path' => 'regions/mexico.jpg',
            'banner_alt' => 'Mexico region',
            'is_active' => true,
        ]);

        Region::query()->create([
            'name' => 'Peru',
            'slug' => 'peru',
            'description' => 'Herencia ancestral y tradiciones vivas.',
            'banner_disk' => 'public',
            'banner_path' => 'regions/peru.jpg',
            'banner_alt' => 'Peru region',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/home/featured-regions');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.eyebrow', 'Explora nuestras culturas')
            ->assertJsonPath('data.title', 'De donde vive el arte')
            ->assertJsonStructure([
                'data' => [
                    'eyebrow',
                    'title',
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'image_url',
                            'image_path',
                            'image_alt',
                            'products_count',
                            'links' => [
                                'region_detail',
                                'catalog',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
