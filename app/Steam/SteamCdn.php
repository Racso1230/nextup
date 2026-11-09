<?php

namespace App\Steam;

/**
 * The one place that knows where Steam serves game art from. The images are hotlinked, never copied.
 * If Steam moves the CDN, this is the only file to change.
 */
final class SteamCdn
{
    private const string ICON_BASE = 'https://media.steampowered.com/steamcommunity/public/images/apps';

    /** The small community icon (about 32px) for a game, or null when Steam gave us no hash. */
    public static function iconUrl(int $appId, ?string $iconHash): ?string
    {
        if ($iconHash === null || $iconHash === '') {
            return null;
        }

        return self::ICON_BASE."/{$appId}/{$iconHash}.jpg";
    }
}
