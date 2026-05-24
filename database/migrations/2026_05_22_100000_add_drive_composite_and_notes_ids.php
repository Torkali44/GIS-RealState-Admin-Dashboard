<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_houses', function (Blueprint $table) {
            if (! Schema::hasColumn('property_houses', 'drive_word_file_id')) {
                $table->string('drive_word_file_id')->nullable()->after('drive_pdf_id');
            }
        });

        Schema::table('inspection_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('inspection_photos', 'drive_composite_file_id')) {
                $table->string('drive_composite_file_id')->nullable()->after('drive_file_id');
            }
            if (! Schema::hasColumn('inspection_photos', 'drive_notes_file_id')) {
                $table->string('drive_notes_file_id')->nullable()->after('drive_composite_file_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_houses', function (Blueprint $table) {
            if (Schema::hasColumn('property_houses', 'drive_word_file_id')) {
                $table->dropColumn('drive_word_file_id');
            }
        });

        Schema::table('inspection_photos', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('inspection_photos', 'drive_composite_file_id') ? 'drive_composite_file_id' : null,
                Schema::hasColumn('inspection_photos', 'drive_notes_file_id') ? 'drive_notes_file_id' : null,
            ]);
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
