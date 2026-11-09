<?php

namespace App\Igdb;

use App\Steam\Narrow;

final readonly class TimeToBeat
{
    /** All values are seconds. Null means unknown. */
    public function __construct(
        public ?int $hastily,
        public ?int $normally,
        public ?int $completely,
    ) {}

    /**
     * Returns null when no value is usable, so a game without data is simply absent from the result.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $hastily = self::seconds($data, 'hastily');
        $normally = self::seconds($data, 'normally');
        $completely = self::seconds($data, 'completely');

        if ($hastily === null && $normally === null && $completely === null) {
            return null;
        }

        return new self($hastily, $normally, $completely);
    }

    /**
     * A missing value and a 0 both mean "unknown". Nothing is beaten in zero seconds, and the scorer treats
     * unknown as neutral, so letting a 0 through would read as a very short game.
     *
     * @param  array<array-key, mixed>  $data
     */
    private static function seconds(array $data, string $key): ?int
    {
        $value = Narrow::int($data, $key);

        return $value !== null && $value > 0 ? $value : null;
    }
}
