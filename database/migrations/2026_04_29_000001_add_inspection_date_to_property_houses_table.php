<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_houses', function (Blueprint $table): void {
            if (! Schema::hasColumn('property_houses', 'inspection_date')) {
                $table->date('inspection_date')->nullable()->after('reference_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_houses', function (Blueprint $table): void {
            if (Schema::hasColumn('property_houses', 'inspection_date')) {
                $table->dropColumn('inspection_date');
            }
        });
    }
};
