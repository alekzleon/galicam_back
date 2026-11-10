<?php

namespace Tests\Feature;

use App\Models\Region;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PublicRegionsMenuTest extends TestCase
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

    public function test_regions_menu_returns_active_regions_as_plain_array(): void
    {
        $activeRegion = Region::query()->create([
            'name' => 'AAA Contract Active Region',
            'slug' => 'contract-active-region',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Region::query()->create([
            'name' => 'AAA Contract Inactive Region',
            'slug' => 'contract-inactive-region',
            'sort_order' => 0,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/regions/menu');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'products_count',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'id' => $activeRegion->id,
                'name' => 'AAA Contract Active Region',
                'slug' => 'contract-active-region',
                'products_count' => 0,
            ])
            ->assertJsonMissing([
                'slug' => 'contract-inactive-region',
            ]);
    }
}
