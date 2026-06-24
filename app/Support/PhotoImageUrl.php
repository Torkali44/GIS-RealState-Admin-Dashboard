<?php

namespace App\Support;

use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use Illuminate\Http\Request;

/**
 * روابط صور للـ <img> — توكن HMAC (لا session ولا signed URL من Laravel).
 */
class PhotoImageUrl
{
    public static function make(PropertyHouse $house, InspectionPhoto $photo, bool $composite = false, int $thumbSize = 0): string
    {
        $c = $composite ? 1 : 0;
        $expires = now()->addHours(24)->timestamp;

        $params = [
            'house' => $house->id,
            'photo' => $photo->id,
            'c' => $c,
            'e' => $expires,
            't' => self::token((int) $house->id, (int) $photo->id, $c, $expires),
        ];

        if ($thumbSize > 0) {
            $params['s'] = min(800, max(80, $thumbSize));
        }

        return route('admin.houses.photos.image', $params);
    }

    public static function thumb(PropertyHouse $house, InspectionPhoto $photo, bool $composite = false, int $size = 420): string
    {
        return self::make($house, $photo, $composite, $size);
    }

    public static function token(int $houseId, int $photoId, int $c, int $expires): string
    {
        return hash_hmac('sha256', "{$houseId}|{$photoId}|{$c}|{$expires}", (string) config('app.key'));
    }

    public static function allows(Request $request, PropertyHouse $house, InspectionPhoto $photo): bool
    {
        $user = $request->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $expires = (int) $request->query('e', 0);
        $token = (string) $request->query('t', '');
        $c = $request->boolean('c') ? 1 : 0;

        if ($expires < time() || $token === '') {
            return false;
        }

        $expected = self::token((int) $house->id, (int) $photo->id, $c, $expires);

        return hash_equals($expected, $token);
    }
}
