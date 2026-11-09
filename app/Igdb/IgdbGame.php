<?php

namespace App\Igdb;

use App\Enums\TagType;
use App\Steam\Narrow;
use Carbon\CarbonImmutable;

final readonly class IgdbGame
{
    /** IGDB field to tag type. Each is requested expanded, e.g. `genres.name`. */
    public const TAG_FIELDS = [
        'genres' => TagType::Genre,
        'themes' => TagType::Theme,
        'game_modes' => TagType::GameMode,
        'player_perspectives' => TagType::Perspective,
        'keywords' => TagType::Keyword,
    ];

    /**
     * @param  list<int>  $similarIgdbIds
     * @param  list<IgdbTag>  $tags
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $slug,
        public ?CarbonImmutable $firstReleaseDate,
        public ?string $coverImageId,
        public array $similarIgdbIds,
        public array $tags,
    ) {}

    /**
     * Returns null when the entry has no usable id or name. Every other field degrades to null or empty.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $id = Narrow::int($data, 'id');
        $name = Narrow::nonEmptyString($data, 'name');

        if ($id === null || $id < 1 || $name === null) {
            return null;
        }

        $timestamp = Narrow::int($data, 'first_release_date');

        return new self(
            id: $id,
            name: $name,
            slug: Narrow::nonEmptyString($data, 'slug'),
            firstReleaseDate: $timestamp === null ? null : CarbonImmutable::createFromTimestampUTC($timestamp),
            coverImageId: self::coverImageId($data['cover'] ?? null),
            similarIgdbIds: self::ids($data['similar_games'] ?? null),
            tags: self::tags($data),
        );
    }

    /** The id ends up in a URL path, so only IGDB's own alphabet is accepted. */
    private static function coverImageId(mixed $cover): ?string
    {
        if (! is_array($cover)) {
            return null;
        }

        $imageId = Narrow::nonEmptyString($cover, 'image_id');

        return $imageId !== null && preg_match('/^[A-Za-z0-9_-]+$/', $imageId) === 1 ? $imageId : null;
    }

    /** @return list<int> */
    private static function ids(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            if (is_int($value) && $value > 0) {
                $ids[$value] = $value;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<IgdbTag>
     */
    private static function tags(array $data): array
    {
        $tags = [];

        foreach (self::TAG_FIELDS as $field => $type) {
            $entries = $data[$field] ?? null;

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                $tag = IgdbTag::fromValue($type, $entry);

                if ($tag !== null) {
                    $tags[$type->value.':'.$tag->igdbId] ??= $tag;
                }
            }
        }

        return array_values($tags);
    }
}
