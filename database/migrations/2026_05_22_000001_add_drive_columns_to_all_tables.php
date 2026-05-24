<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // أعمدة للمنازل
        Schema::table('property_houses', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable()->after('notes');
            $table->string('drive_pdf_id')->nullable()->after('drive_folder_id');
        });

        // أعمدة للأقسام
        Schema::table('inspection_areas', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable()->after('sort_order');
        });

        // أعمدة للصور
        Schema::table('inspection_photos', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('upload_file_key');
        });
    }

    public function down(): void
    {
        Schema::table('property_houses', function (Blueprint $table) {
            $table->dropColumn(['drive_folder_id', 'drive_pdf_id']);
        });
        Schema::table('inspection_areas', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
        Schema::table('inspection_photos', function (Blueprint $table) {
            $table->dropColumn('drive_file_id');
        });
    }
};
