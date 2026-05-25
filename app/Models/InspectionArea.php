<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionArea extends Model
{
    protected $fillable = [
        'property_house_id',
        'name',
        'sort_order',
        'drive_folder_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function propertyHouse(): BelongsTo
    {
        return $this->belongsTo(PropertyHouse::class);
    }

    public function photos(): HasMany
    {
       
        return $this->hasMany(InspectionPhoto::class)->orderBy('sort_order')->orderBy('id');
    }
}
