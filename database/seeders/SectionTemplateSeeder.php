<?php

namespace Database\Seeders;

use App\Models\SectionTemplate;
use Illuminate\Database\Seeder;

class SectionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'الفناء الخارجي',
            'المدخل الرئيسي',
            'الصالة',
            'المطبخ',
            'غرفة الطعام',
            'دورة المياه',
            'غرفة النوم 1',
            'غرفة النوم 2',
            'غرفة النوم 3',
            'الممرات',
            'الدرج',
            'السطح',
            'الفناء الخلفي',
            'غرفة الغسيل',
            'البلكونة',
        ];

        foreach ($defaults as $index => $name) {
            SectionTemplate::firstOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1]
            );
        }
    }
}
