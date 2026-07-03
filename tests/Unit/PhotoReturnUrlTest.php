<?php

namespace Tests\Unit;

use App\Models\InspectionArea;
use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use App\Support\PhotoReturnUrl;
use Tests\TestCase;

class PhotoReturnUrlTest extends TestCase
{
    public function test_converts_photos_panel_url_to_house_show(): void
    {
        $house = new PropertyHouse;
        $house->id = 3;
        $photo = new InspectionPhoto;
        $photo->id = 689;
        $photo->inspection_area_id = 9;
        $area = new InspectionArea;
        $area->id = 9;

        $bad = 'http://127.0.0.1:8000/admin/houses/3/areas/9/photos-panel?area_9_page=1&photos_per_page=40#photo-689';
        $fixed = PhotoReturnUrl::normalize($bad, $house, $photo);

        $this->assertNotNull($fixed);
        $this->assertStringContainsString('/admin/houses/3?', $fixed);
        $this->assertStringContainsString('area_9_page=1', $fixed);
        $this->assertStringContainsString('photos_per_page=40', $fixed);
        $this->assertStringEndsWith('#photo-689', $fixed);
        $this->assertStringNotContainsString('photos-panel', $fixed);

        $expected = PhotoReturnUrl::forHousePhoto($house, $area, $photo, 1, 40);
        $this->assertSame($expected, $fixed);
    }
}
