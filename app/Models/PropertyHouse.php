<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PropertyHouse extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'client_name',
        'address',
        'reference_code',
        'inspection_date',
        'notes',
    ];
  protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PropertyHouse $house): void {
            $areaIds = $house->inspectionAreas()->pluck('id');
            if ($areaIds->isEmpty()) {
                return;
            }
            InspectionPhoto::query()
                ->whereIn('inspection_area_id', $areaIds)
                ->chunkById(50, function (Collection $photos): void {
                    $photos->each->delete();
                });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inspectionAreas(): HasMany
    {
        return $this->hasMany(InspectionArea::class)->orderBy('sort_order')->orderBy('id');
    }

    public function photos(): HasManyThrough
    {
        return $this->hasManyThrough(
            InspectionPhoto::class,
            InspectionArea::class,
            'property_house_id',
            'inspection_area_id',
            'id',
            'id'
        );
    }
}
