<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            [
                'ar_name' => 'أبيض',
                'en_name' => 'White',
                'hex_code' => '#FFFFFF',
                'status' => true,
            ],
            [
                'ar_name' => 'أسود',
                'en_name' => 'Black',
                'hex_code' => '#000000',
                'status' => true,
            ],
            [
                'ar_name' => 'أحمر',
                'en_name' => 'Red',
                'hex_code' => '#FF0000',
                'status' => true,
            ],
            [
                'ar_name' => 'أزرق',
                'en_name' => 'Blue',
                'hex_code' => '#0000FF',
                'status' => true,
            ],
            [
                'ar_name' => 'أخضر',
                'en_name' => 'Green',
                'hex_code' => '#00FF00',
                'status' => true,
            ],
            [
                'ar_name' => 'أصفر',
                'en_name' => 'Yellow',
                'hex_code' => '#FFFF00',
                'status' => true,
            ],
            [
                'ar_name' => 'رمادي',
                'en_name' => 'Gray',
                'hex_code' => '#808080',
                'status' => true,
            ],
            [
                'ar_name' => 'بني',
                'en_name' => 'Brown',
                'hex_code' => '#A52A2A',
                'status' => true,
            ],
            [
                'ar_name' => 'بنفسجي',
                'en_name' => 'Purple',
                'hex_code' => '#800080',
                'status' => true,
            ],
            [
                'ar_name' => 'وردي',
                'en_name' => 'Pink',
                'hex_code' => '#FFC0CB',
                'status' => true,
            ],
            [
                'ar_name' => 'برتقالي',
                'en_name' => 'Orange',
                'hex_code' => '#FFA500',
                'status' => true,
            ],
            [
                'ar_name' => 'بيج',
                'en_name' => 'Beige',
                'hex_code' => '#F5F5DC',
                'status' => true,
            ],
        ];

        foreach ($colors as $color) {
            Color::create($color);
        }
    }
}
