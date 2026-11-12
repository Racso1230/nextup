<?php

namespace App\Recommender;

/**
 * One ranked candidate: its total score, every signal's contribution, and the taste evidence the explanation
 * names ("Because you put 140 hours into Hades and Dead Cells: roguelike, short runs").
 */
final readonly class Ranked
{
    /**
     * @param  list<Contribution>  $contributions  one per SignalName, in enum order
     * @param  list<array{name: string, hours: float}>  $evidenceGames  played games that drive the taste match most
     * @param  list<string>  $evidenceTags  shared tag names that drive the taste match most
     * @param  ?Feedback  $feedback  the user's latest feedback on this game, for the explanation
     */
    public function __construct(
        public GameFacts $game,
        public float $score,
        public array $contributions,
        public array $evidenceGames = [],
        public array $evidenceTags = [],
        public ?Feedback $feedback = null,
    ) {}

    public function contribution(SignalName $signal): ?Contribution
    {
        foreach ($this->contributions as $contribution) {
            if ($contribution->signal === $signal) {
                return $contribution;
            }
        }

        return null;
    }
}
