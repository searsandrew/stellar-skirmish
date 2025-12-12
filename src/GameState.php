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
        public ?GameEndReason $endReason = null,
        /** @var array<int, Corporation|null> playerId => Corporation */
        public array $corporations = [],
        /** @var array<int, Mercenary[]> playerId => mercenaries owned */
        public array $mercenaries = [],
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
            'end_reason'            => $this->endReason?->value,
            'corporations'          => array_map(
                fn (?Corporation $corp) => $corp?->toArray(),
                $this->corporations
            ),
            'mercenaries'           => array_map(
                fn (array $mercenaries) => array_map(fn (Mercenary $merc) => $merc->toArray(), $mercenaries),
                $this->mercenaries
            ),
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

        $corporations = [];
        foreach ($data['corporations'] ?? [] as $playerId => $corp) {
            $corporations[(int) $playerId] = $corp
                ? Corporation::fromArray($corp)
                : null;
        }

        $mercenaries = [];
        foreach($data['mercenaries'] ?? [] as $playerId => $mercs) {
            $mercenaries[(int) $playerId] = array_map(
                fn (array $merc) => Mercenary::fromArray($merc),
                $mercs
            );
        }

        $endReason = null;
        if (isset($data['end_reason']) && $data['end_reason'] !== null) {
            $endReason = GameEndReason::from($data['end_reason']);
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
            endReason: $endReason,
            corporations: $corporations,
            mercenaries: $mercenaries,
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

    /**
     * Defensive helper.
     * Flags us immediately if at least one player has an empty hand while at least one other player still has cards.
     */
    public function anyPlayerOutOfCardsEarly(): bool
    {
        $empty = 0;
        $nonEmpty = 0;

        foreach ($this->hands as $hand) {
            if ($hand === []) {
                $empty++;
            } else {
                $nonEmpty++;
            }
        }

        return $empty > 0 && $nonEmpty > 0;
    }

    /**
     * All planets that were never claimed by any player.
     *
     * This includes both planets still in the deck and any that effectively remain unclaimed at the end of the game.
     * (e.g., discarded planetPot)
     */
    public function unclaimedPlanets(): array
    {
        $claimedIds = [];

        foreach ($this->claimedPlanets as $planets) {
            foreach ($planets as $planet) {
                $claimedIds[$planet->id] = true;
            }
        }

        $unclaimed = [];
        foreach ($this->planetDeck as $planet) {
            if (!isset($unclaimedIds[$planet->id])) {
                $unclaimed[] = $planet;
            }
        }

        return $unclaimed;
    }

    /**
     * Raw VP sum (no corp multipliers, set bonuses may or may not be included depending on how you apply them).
     *
     * @return array<int, int|float>
     */
    public function rawScores(): array
    {
        $scores = [];

        foreach ($this->claimedPlanets as $playerId => $planets) {
            $scores[$playerId] = array_sum(
                array_map(fn (Planet $p) => $p->victoryPoints, $planets)
            );
        }

        return $scores;
    }

    /**
     * Calculate per-class base VP for a player (before corp multipliers).
     *
     * @return array<string, float> class value => vp
     */
    private function baseVpByClassForPlayer(GameState $state, int $playerId): array
    {
        $totals = [];

        foreach ($state->claimedPlanets[$playerId] ?? [] as $planet) {
            $classKey = $planet->class?->value ?? 'none';

            if (!isset($totals[$classKey])) {
                $totals[$classKey] = 0.0;
            }

            $totals[$classKey] += $planet->victoryPoints;
        }

        $this->applyClassSetBonuses($state, $playerId, $totals);

        return $totals;
    }

    /**
     * Final scores with corporation multipliers applied.
     *
     * @return array<int, float> playerId => final score
     */
    public function finalScores(GameState $state): array
    {
        $scores = [];

        for ($playerId = 1; $playerId <= $state->playerCount; $playerId++) {
            $corp  = $state->corporations[$playerId] ?? null;
            $base  = $this->baseVpByClassForPlayer($state, $playerId);
            $total = 0.0;

            foreach ($base as $classValue => $vp) {
                $class = $classValue === 'none' ? null : PlanetClass::from($classValue);
                $mult  = $corp?->multiplierForClass($class) ?? 1.0;

                $total += $vp * $mult;
            }

            $scores[$playerId] = $total;
        }

        return $scores;
    }

    private function applyClassSetBonuses(GameState $state, int $playerId, array &$totals): void
    {
        $planets = $state->claimedPlanets[$playerId] ?? [];

        foreach ($planets as $planet) {
            foreach ($planet->abilities as $ability) {
                if ($ability->type !== PlanetAbilityType::ClassSetBonus) {
                    continue;
                }

                $targetClassValue = $ability->params['class'] ?? null;
                $thresholds       = $ability->params['thresholds'] ?? [];

                if ($targetClassValue === null || !is_array($thresholds)) {
                    continue;
                }

                // Count how many planets of that class the player has
                $count = 0;
                foreach ($planets as $p) {
                    if ($p->class?->value === $targetClassValue) {
                        $count++;
                    }
                }

                // Find the highest threshold <= count
                ksort($thresholds);
                $bonus = 0;
                foreach ($thresholds as $threshold => $value) {
                    if ($count >= $threshold) {
                        $bonus = $value;
                    }
                }

                if ($bonus !== 0) {
                    if (!isset($totals[$targetClassValue])) {
                        $totals[$targetClassValue] = 0.0;
                    }
                    $totals[$targetClassValue] += $bonus;
                }
            }
        }
    }
}
