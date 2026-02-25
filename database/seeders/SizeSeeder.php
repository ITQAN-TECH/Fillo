<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            [
                'ar_name' => 'صغير جداً',
                'en_name' => 'Extra Small',
                'code' => 'XS',
                'status' => true,
            ],
            [
                'ar_name' => 'صغير',
                'en_name' => 'Small',
                'code' => 'S',
                'status' => true,
            ],
            [
                'ar_name' => 'متوسط',
                'en_name' => 'Medium',
                'code' => 'M',
                'status' => true,
            ],
            [
                'ar_name' => 'كبير',
                'en_name' => 'Large',
                'code' => 'L',
                'status' => true,
            ],
            [
                'ar_name' => 'كبير جداً',
                'en_name' => 'Extra Large',
                'code' => 'XL',
                'status' => true,
            ],
            [
                'ar_name' => 'كبير جداً جداً',
                'en_name' => 'Double Extra Large',
                'code' => 'XXL',
                'status' => true,
            ],
            [
                'ar_name' => 'ثلاثة إكس لارج',
                'en_name' => 'Triple Extra Large',
                'code' => '3XL',
                'status' => true,
            ],
        ];

        foreach ($sizes as $size) {
            Size::create($size);
        }
    }
}
