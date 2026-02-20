<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class FaqPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Faq::firstOrCreate([
            'ar_question' => 'السؤال الأول باللغة العربية',
            'en_question' => 'Question 1 in English',
            'ar_answer' => 'الجواب الأول باللغة العربية',
            'en_answer' => 'Answer 1 in English',
            'order' => 1,
        ]);

        Faq::firstOrCreate([
            'ar_question' => 'السؤال الثاني باللغة العربية',
            'en_question' => 'Question 2 in English',
            'ar_answer' => 'الجواب الثاني باللغة العربية',
            'en_answer' => 'Answer 2 in English',
            'order' => 2,
        ]);

        Faq::firstOrCreate([
            'ar_question' => 'السؤال الثالث باللغة العربية',
            'en_question' => 'Question 3 in English',
            'ar_answer' => 'الجواب الثالث باللغة العربية',
            'en_answer' => 'Answer 3 in English',
            'order' => 3,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-faqs',
            'display_name' => 'عرض الأسئلة الشائعة',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-faqs',
            'display_name' => 'إنشاء الأسئلة الشائعة',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-faqs',
            'display_name' => 'تعديل الأسئلة الشائعة',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-faqs',
            'display_name' => 'حذف الأسئلة الشائعة',
        ]);
    }
}
