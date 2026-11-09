<?php

namespace App\Steam;

final readonly class PlayerSummary
{
    public function __construct(
        public string $steamId,
        public string $personaName,
        public ?string $avatarFullUrl,
        public ?string $profileUrl,
        public bool $isPublic,
    ) {}

    /**
     * Returns null when the entry has no usable steamid or persona name.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $steamId = Narrow::string($data, 'steamid');
        $personaName = Narrow::string($data, 'personaname');

        if ($steamId === null || preg_match('/^\d{1,20}$/', $steamId) !== 1 || $personaName === null) {
            return null;
        }

        return new self(
            steamId: $steamId,
            personaName: $personaName,
            avatarFullUrl: Narrow::nonEmptyString($data, 'avatarfull'),
            profileUrl: Narrow::nonEmptyString($data, 'profileurl'),
            isPublic: Narrow::int($data, 'communityvisibilitystate') === 3,
        );
    }
}
