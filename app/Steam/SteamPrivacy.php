<?php

namespace App\Steam;

/**
 * Where a user changes who can see their games on Steam, and what to click.
 * The names below match Steam's profile privacy page. Re-check them if Steam redesigns it.
 */
final class SteamPrivacy
{
    public const string SETTINGS_URL = 'https://steamcommunity.com/my/edit/settings';

    /** @return list<string> */
    public static function steps(): array
    {
        return [
            'Open your Steam privacy settings.',
            'Set "Game details" to Public.',
            'Untick "Always keep my total playtime private even if users can see my game details".',
            'Come back here and choose "Check again".',
        ];
    }
}
