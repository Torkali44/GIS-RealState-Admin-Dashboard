<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فصل التصنيفات عن الأقسام: التصنيفات أصبحت عالمية،
     * يستخدمها المستخدم على أي صورة بغض النظر عن القسم.
     */
    public function up(): void
    {
        Schema::table('inspection_areas', function (Blueprint $table) {
            if (Schema::hasColumn('inspection_areas', 'note_category_id')) {
                try {
                    $table->dropForeign(['note_category_id']);
                } catch (\Throwable) {
                    // قد لا يوجد المفتاح الأجنبي على بعض القواعد
                }
                $table->dropColumn('note_category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('inspection_areas', 'note_category_id')) {
                $table->foreignId('note_category_id')
                    ->nullable()
                    ->after('property_house_id')
                    ->constrained('note_categories')
                    ->nullOnDelete();
            }
        });
    }
};
