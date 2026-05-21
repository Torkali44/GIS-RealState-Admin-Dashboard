<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_category_id')
                ->constrained('note_categories')
                ->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['note_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_templates');
    }
};
