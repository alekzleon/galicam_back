<?php

namespace Database\Seeders;

use App\Models\Artisan;
use App\Models\Region;
use Illuminate\Database\Seeder;

class ArtisanSeeder extends Seeder
{
    public function run(): void
    {
        // Sample profiles; replace with verified artisan information before publishing.
        $artisans = [
            [
                'slug' => 'taller-barro-de-espana',
                'name' => 'Taller Barro de España',
                'region' => 'espana',
                'history' => 'Perfil de ejemplo de un taller dedicado a la alfarería y a la elaboración manual de piezas de cerámica inspiradas en la tradición española.',
                'specialty' => 'Alfarería y cerámica',
            ],
            [
                'slug' => 'taller-raices-de-mexico',
                'name' => 'Taller Raíces de México',
                'region' => 'mexico',
                'history' => 'Perfil de ejemplo de un taller dedicado al tejido y al bordado manual, inspirado en la diversidad textil de México.',
                'specialty' => 'Textiles y bordado',
            ],
            [
                'slug' => 'taller-fibras-de-brasil',
                'name' => 'Taller Fibras de Brasil',
                'region' => 'brasil',
                'history' => 'Perfil de ejemplo de un taller dedicado a la cestería y al trabajo manual con fibras naturales, inspirado en las tradiciones artesanales de Brasil.',
                'specialty' => 'Cestería y fibras naturales',
            ],
        ];

        foreach ($artisans as $index => $artisan) {
            $region = Region::query()->where('slug', $artisan['region'])->firstOrFail();

            Artisan::updateOrCreate(['slug' => $artisan['slug']], [
                'region_id' => $region->id,
                'name' => $artisan['name'],
                'history' => $artisan['history'],
                'sort_order' => $index + 1,
                'is_active' => true,
                'metadata' => [
                    'is_sample' => true,
                    'specialty' => $artisan['specialty'],
                ],
            ]);
        }
    }
}
