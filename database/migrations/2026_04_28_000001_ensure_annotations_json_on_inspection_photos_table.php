<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inspection_photos', 'annotations_json')) {
            Schema::table('inspection_photos', function (Blueprint $table) {
                $table->json('annotations_json')->nullable()->after('tip_y');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inspection_photos', 'annotations_json')) {
            Schema::table('inspection_photos', function (Blueprint $table) {
                $table->dropColumn('annotations_json');
            });
        }
    }
};
