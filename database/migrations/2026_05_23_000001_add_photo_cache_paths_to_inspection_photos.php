<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('inspection_photos', 'local_cached_path')) {
                $table->string('local_cached_path', 500)->nullable()->after('drive_notes_file_id');
            }
            if (! Schema::hasColumn('inspection_photos', 'processed_cache_path')) {
                $table->string('processed_cache_path', 500)->nullable()->after('local_cached_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_photos', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('inspection_photos', 'local_cached_path') ? 'local_cached_path' : null,
                Schema::hasColumn('inspection_photos', 'processed_cache_path') ? 'processed_cache_path' : null,
            ]);
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
