<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_photos', function (Blueprint $table) {
            $table->string('upload_batch_id', 100)->nullable()->after('original_filename');
            $table->string('upload_file_key', 500)->nullable()->after('upload_batch_id');
            $table->unique(['inspection_area_id', 'upload_batch_id', 'upload_file_key'], 'inspection_photos_upload_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_photos', function (Blueprint $table) {
            $table->dropUnique('inspection_photos_upload_unique');
            $table->dropColumn(['upload_batch_id', 'upload_file_key']);
        });
    }
};
