<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'sku' => 'TEC-001',
                'name' => 'Mouse inalámbrico',
                'description' => 'Mouse ergonómico con conexión USB.',
                'price_cents' => 1599,
                'stock' => 30,
                'is_active' => true,
            ],
            [
                'sku' => 'TEC-002',
                'name' => 'Teclado mecánico',
                'description' => 'Teclado mecánico con iluminación RGB.',
                'price_cents' => 5499,
                'stock' => 15,
                'is_active' => true,
            ],
            [
                'sku' => 'TEC-003',
                'name' => 'Audífonos Bluetooth',
                'description' => 'Audífonos inalámbricos recargables.',
                'price_cents' => 2999,
                'stock' => 20,
                'is_active' => true,
            ],
            [
                'sku' => 'TEC-004',
                'name' => 'Hub USB-C',
                'description' => 'Adaptador multipuerto USB-C.',
                'price_cents' => 2499,
                'stock' => 25,
                'is_active' => true,
            ],
            [
                'sku' => 'TEC-005',
                'name' => 'Soporte para laptop',
                'description' => 'Soporte ajustable de aluminio.',
                'price_cents' => 1999,
                'stock' => 18,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }
    }
}

