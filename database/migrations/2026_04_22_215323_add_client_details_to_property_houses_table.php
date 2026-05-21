<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_houses', function (Blueprint $blueprint) {
            $blueprint->string('client_name')->nullable()->after('title');
            $blueprint->string('address')->nullable()->after('client_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_houses', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['client_name', 'address']);
        });
    }
};
