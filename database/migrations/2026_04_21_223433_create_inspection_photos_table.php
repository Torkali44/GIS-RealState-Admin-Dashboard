<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_area_id')->constrained('inspection_areas')->cascadeOnDelete();
            $table->string('original_path');
            $table->string('composite_path')->nullable();
            $table->decimal('tip_x', 7, 5)->nullable();
            $table->decimal('tip_y', 7, 5)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('original_filename')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_photos');
    }
};
