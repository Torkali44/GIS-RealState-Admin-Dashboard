<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('inspection_photos', 'notes_json')) {
                $table->json('notes_json')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_photos', function (Blueprint $table) {
            if (Schema::hasColumn('inspection_photos', 'notes_json')) {
                $table->dropColumn('notes_json');
            }
        });
    }
};
