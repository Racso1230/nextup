<?php

namespace App\Igdb;

use App\Enums\TagType;
use App\Steam\Narrow;

final readonly class IgdbTag
{
    public function __construct(
        public TagType $type,
        public int $igdbId,
        public string $name,
    ) {}

    /**
     * An expanded entry such as `{"id": 12, "name": "Role-playing (RPG)"}`.
     * Returns null for anything else, including a bare id that was not expanded.
     */
    public static function fromValue(TagType $type, mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }

        $id = Narrow::int($value, 'id');
        $name = Narrow::nonEmptyString($value, 'name');

        return $id === null || $id < 1 || $name === null ? null : new self($type, $id, $name);
    }
}
