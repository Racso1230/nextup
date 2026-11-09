<?php

namespace App\Igdb;

/**
 * The one place that knows where IGDB serves images from. The images are hotlinked, never copied.
 */
final class IgdbImages
{
    private const string BASE = 'https://images.igdb.com/igdb/image/upload';

    /** The cover URL for an IGDB image id, or null when the game has no cover. */
    public static function coverUrl(?string $imageId, string $size = 'cover_big'): ?string
    {
        if ($imageId === null || $imageId === '') {
            return null;
        }

        return self::BASE."/t_{$size}/{$imageId}.jpg";
    }
}
