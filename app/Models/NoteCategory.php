<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(NoteTemplate::class)->orderBy('sort_order')->orderBy('id');
    }
}
