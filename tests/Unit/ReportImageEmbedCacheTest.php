<?php

namespace Tests\Unit;

use App\Models\InspectionPhoto;
use App\Support\ReportImageEmbedCache;
use Tests\TestCase;

class ReportImageEmbedCacheTest extends TestCase
{
    public function test_cache_path_is_stable_for_same_inputs(): void
    {
        $photo = new InspectionPhoto(['id' => 42]);
        $photo->setAttribute('updated_at', '2026-06-01 12:00:00');

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gis-test-'.uniqid('', true).'.jpg';
        file_put_contents($tmp, 'fake');

        $a = ReportImageEmbedCache::cachePath($photo, $tmp, 1100, 65);
        $b = ReportImageEmbedCache::cachePath($photo, $tmp, 1100, 65);

        $this->assertSame($a, $b);

        @unlink($tmp);
    }

    public function test_is_valid_cached_file_rejects_missing_file(): void
    {
        $this->assertFalse(ReportImageEmbedCache::isValidCachedFile('/nonexistent/path.jpg'));
    }
}
