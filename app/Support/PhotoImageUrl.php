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
    public static function make(PropertyHouse $house, InspectionPhoto $photo, bool $composite = false): string
    {
        $c = $composite ? 1 : 0;
        $expires = now()->addHours(24)->timestamp;

        return route('admin.houses.photos.image', [
            'house' => $house->id,
            'photo' => $photo->id,
            'c' => $c,
            'e' => $expires,
            't' => self::token((int) $house->id, (int) $photo->id, $c, $expires),
        ]);
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
