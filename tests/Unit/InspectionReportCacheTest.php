<?php

namespace Tests\Unit;

use App\Models\PropertyHouse;
use App\Models\User;
use App\Support\InspectionReportCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionReportCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_relative_path_is_scoped_to_house(): void
    {
        $user = User::factory()->create();
        $house = PropertyHouse::create([
            'user_id' => $user->id,
            'title' => 'Test House',
        ]);

        $path = InspectionReportCache::relativePath($house);

        $this->assertStringStartsWith('reports/inspection-'.$house->id.'-', $path);
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertStringContainsString(InspectionReportCache::versionStamp($house), $path);
    }
}
