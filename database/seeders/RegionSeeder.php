<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['España', 'espana', 'ES', 'EUR', 'es', 'Artesanía española inspirada en sus tradiciones de cerámica, alfarería, textiles y trabajo en cuero.'],
            ['México', 'mexico', 'MX', 'MXN', 'es', 'Artesanía mexicana que reúne tradiciones de alfarería, textiles, cestería y talla en madera.'],
            ['Brasil', 'brasil', 'BR', 'BRL', 'pt', 'Artesanía brasileña vinculada a sus tradiciones de cerámica, tejidos, fibras naturales y trabajo en madera.'],
            ['Perú', 'peru', 'PE', 'PEN', 'es', 'Artesanía peruana con tradiciones de tejido andino, cerámica, platería y tallado.'],
            ['Colombia', 'colombia', 'CO', 'COP', 'es', 'Artesanía colombiana que reúne tradiciones de tejido, cestería, cerámica y trabajo en fibras naturales.'],
        ];

        foreach ($regions as $index => [$name, $slug, $country, $currency, $language, $description]) {
            Region::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'sort_order' => $index + 1,
                'is_active' => true,
                'metadata' => [
                    'country_code' => $country,
                    'currency' => $currency,
                    'language' => $language,
                ],
            ]);
        }
    }
}
