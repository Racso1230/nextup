<?php

namespace App\Recommender;

use App\Enums\TagType;

/**
 * One IGDB tag on a game. Identity is (type, id): IGDB ids are only unique within a tag type.
 */
final readonly class TagRef
{
    public function __construct(
        public TagType $type,
        public int $id,
        public string $name,
    ) {}

    /** A stable key for vectors, e.g. "genre:12". */
    public function key(): string
    {
        return $this->type->value.':'.$this->id;
    }
}
