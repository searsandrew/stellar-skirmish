<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class GameState
{
    public function __construct(
        public readonly int $playerCount,
        /** @var array<int, int[]> playerId => ship values still in hand */
        public array $hands,
        /** @var Planet[] full planet deck */
        public array $planetDeck,
        /** @var int index of next planet to reveal from deck */
        public int $currentPlanetIndex,
        /** @var Planet[] planets currently at stake due to ties */
        public array $planetPot,
        /** @var array<int, Planet[]> playerId => claimed planets */
        public array $claimedPlanets,
        /** @var array<int, int|null> playerId => last chosen card for current battle */
        public array $currentPlays,
        public bool $gameOver = false,
    ) {}

    /**
     * Helper for storing to DB as JSON.
     */
    public function toArray(): array
    {
        return [
            'player_count'          => $this->playerCount,
            'hands'                 => $this->hands,
            'planet_deck'           => array_map(fn (Planet $p) => $p->toArray(), $this->planetDeck),
            'current_planet_index'  => $this->currentPlanetIndex,
            'planet_pot'            => array_map(fn (Planet $p) => $p->toArray(), $this->planetPot),
            'claimed_planets'       => array_map(
                fn (array $planets) => array_map(fn (Planet $p) => $p->toArray(), $planets),
                $this->claimedPlanets
            ),
            'current_plays'         => $this->currentPlays,
            'game_over'             => $this->gameOver,
        ];
    }

    public static function fromArray(array $data): self
    {
        $deck = array_map(fn (array $p) => Planet::fromArray($p), $data['planet_deck']);
        $pot  = array_map(fn (array $p) => Planet::fromArray($p), $data['planet_pot']);

        $claimed = [];
        foreach ($data['claimed_planets'] as $playerId => $planets) {
            $claimed[(int) $playerId] = array_map(
                fn (array $p) => Planet::fromArray($p),
                $planets
            );
        }

        return new self(
            playerCount: (int) $data['player_count'],
            hands: $data['hands'],
            planetDeck: $deck,
            currentPlanetIndex: (int) $data['current_planet_index'],
            planetPot: $pot,
            claimedPlanets: $claimed,
            currentPlays: $data['current_plays'],
            gameOver: (bool) $data['game_over'],
        );
    }

    /**
     * @return array<int, int> playerId => total VP
     */
    public function scores(): array
    {
        $scores = [];

        foreach ($this->claimedPlanets as $playerId => $planets) {
            $scores[$playerId] = array_sum(
                array_map(fn (Planet $p) => $p->victoryPoints, $planets)
            );
        }

        return $scores;
    }

    public function allHandsEmpty(): bool
    {
        foreach ($this->hands as $hand) {
            if ($hand !== []) {
                return false;
            }
        }

        return true;
    }
}
