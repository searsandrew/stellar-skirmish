<?php

declare(strict_types=1);

namespace StellarSkirmish;

use StellarSkirmish\Exceptions\GameOver;
use StellarSkirmish\Exceptions\InvalidMove;

final class GameEngine
{
    public function startNewGame(GameConfig $config): GameState
    {
        $hands          = [];
        $claimed        = [];
        $plays          = [];
        $mercenaries    = [];
        $mercPlays      = [];
        $corporations   = [];

        for ($p = 1; $p <= $config->playerCount; $p++) {
            $hands[$p]          = array_values($config->fleetValues);
            $claimed[$p]        = [];
            $plays[$p]          = null;
            $mercenaries[$p]    = [];
            $mercPlays[$p]      = null;
            $corporations[$p]   = null;
        }

        return new GameState(
            playerCount: $config->playerCount,
            hands: $hands,
            planetDeck: array_values($config->planets),
            currentPlanetIndex: 0,
            planetPot: [],
            claimedPlanets: $claimed,
            currentPlays: $plays,
            gameOver: false,
            endReason: null,
            corporations: $corporations,
            mercenaries: $mercenaries,
            currentMercPlays: $mercPlays,
        );
    }

    /**
     * Ensure there is at least one planet in the pot before a round.
     */
    private function ensurePlanetInPot(GameState $state): GameState
    {
        if (!empty($state->planetPot)) {
            return $state;
        }

        // planetPot can be empty if there are no planets left to reveal
        if ($state->currentPlanetIndex >= count($state->planetDeck)) {
            return $state;
        }

        $state->planetPot[] = $state->planetDeck[$state->currentPlanetIndex];
        $state->currentPlanetIndex++;

        return $state;
    }

    /**
     * List the cards a player is allowed to play right now.
     * For now, this is just the cards they have in their hand.
     * @return int[]
     * @todo: add status effect which we can enforce (e.g. "can't play this card this turn")
     *
     */
    public function legalCardsForPlayer(GameState $state, int $playerId): array
    {
        return $state->hands[$playerId] ?? [];
    }

    /**
     * Total VP currently in the planetPot.
     */
    public function potTotalVictoryPoints(GameState $state): int
    {
        $total = 0;

        foreach($state->planetPot as $planet) {
            $total += $planet->victoryPoints;
        }

        return $total;
    }

    /**
     * Convenience helper for UI/API: full pot details and size.
     *
     * @return array {
     *     planets: array<int, array>
     *     total_vp: int,
     *     count: int
     * }
     */
    public function potSummary(GameState $state): array
    {
        return [
            'planets'   => array_map(fn (Planet $p) => $p->toArray(), $state->planetPot),
            'total_vp'  => $this->potTotalVictoryPoints($state),
            'count'     => count($state->planetPot),
        ];
    }

    /**
     * Player chooses a card to play for the current battle.
     *
     * When all players have played, the battle is resolved (including tie rules).
     */
    public function playCard(GameState $state, int $playerId, int $cardValue): GameState
    {
        if ($state->gameOver) {
            throw new GameOver('Game is already over.');
        }

        if (!array_key_exists($playerId, $state->hands)) {
            throw new InvalidMove("Player {$playerId} does not exist.");
        }

        if ($state->hands[$playerId] === []) {
            // Defensive: a player with no cards trying to play should never happen.
            $state->gameOver = true;
            $state->endReason = GameEndReason::PlayerOutOfCardsEarly;

            throw new GameOver('Player has no cards left to play.');
        }

        // first player it act this battle triggers planet reveal if needed.
        $state = $this->ensurePlanetInPot($state);

        if (!in_array($cardValue, $state->hands[$playerId], true)) {
            throw new InvalidMove("Player {$playerId} does not have card {$cardValue}.");
        }

        // remove card from hand (always discard; win, tie, or lose)
        $state->hands[$playerId] = $this->removeFirst($state->hands[$playerId], $cardValue);

        // record play
        $state->currentPlays[$playerId] = $cardValue;

        // once all players have played, resolve the battle
        if ($this->allPlayersHavePlayed($state)) {
            $state = $this->resolveBattle($state);

            // If resolveBattle() ended the game (final tie with discarded pot), do not try to apply further end of game logic.
            if (!$state->gameOver) {
                // Defensive: someone ran out of cards early
                if ($state->anyPlayerOutOfCardsEarly()) {
                    $state->gameOver = true;
                    $state->endReason = GameEndReason::PlayerOutOfCardsEarly;
                } elseif ($state->allHandsEmpty()) {
                    $state->gameOver = true;

                    $hasUnclaimed = $state->unclaimedPlanets() !== [];
                    $state->endReason = $hasUnclaimed
                        ? GameEndReason::ShipsExhaustedPlanetsRemaining
                        : GameEndReason::Normal;
                }
            }
        }

        return $state;
    }

    private function awardPlanetToPlayer(GameState $state, int $playerId, Planet $planet): GameState
    {
        $state->claimedPlanets[$playerId][] = $planet;

        // Process on-claim abilities
        foreach ($planet->abilities as $ability) {
            $state = $this->applyPlanetAbilityOnClaim($state, $playerId, $planet, $ability);
        }

        return $state;
    }

    private function applyPlanetAbilityOnClaim(
        GameState $state,
        int $playerId,
        Planet $planet,
        PlanetAbility $ability
    ): GameState {
        return match ($ability->type) {
            PlanetAbilityType::DoubleNextPlanetNoCombat
            => $this->applyDoubleNextPlanetNoCombat($state, $playerId, $ability),
            default
            => $state, // scoring-time abilities handled elsewhere
        };
    }

    private function applyDoubleNextPlanetNoCombat(
        GameState $state,
        int $playerId,
        PlanetAbility $ability
    ): GameState {
        if ($state->currentPlanetIndex >= count($state->planetDeck)) {
            // No planet to double, nothing happens
            return $state;
        }

        $nextPlanet = $state->planetDeck[$state->currentPlanetIndex];
        $state->currentPlanetIndex++;

        // Double VP
        $doubled = new Planet(
            id: $nextPlanet->id,
            victoryPoints: $nextPlanet->victoryPoints * 2,
            name: $nextPlanet->name,
            planetClass: $nextPlanet->planetClass,
            abilities: $nextPlanet->abilities,
        );

        $state->claimedPlanets[$playerId][] = $doubled;

        return $state;
    }

    private function removeFirst(array $values, int $value): array
    {
        $found = false;

        return array_values(array_filter(
            $values,
            function (int $v) use (&$found, $value): bool {
                if (!$found && $v === $value) {
                    $found = true;
                    return false;
                }

                return true;
            }
        ));
    }

    private function allPlayersHavePlayed(GameState $state): bool
    {
        foreach ($state->currentPlays as $play) {
            if ($play === null) {
                return false;
            }
        }
        return true;
    }

    private function resetCurrentPlays(GameState $state): void
    {
        foreach ($state->currentPlays as $pId => $_) {
            $state->currentPlays[$pId] = null;
        }
    }

    private function resetCurrentMercPlays(GameState $state): void
    {
        foreach ($state->currentMercPlays as $playerId => $_) {
            $state->currentMercPlays[$playerId] = null;
        }
    }

    /**
     * Apply mercenary abilities that affect the planet pot BEFORE strength resolution.
     * Currently: DiscardPlanetDrawNew
     */
    private function applyMercenaryPreBattleEffects(GameState $state): GameState
    {
        $hasSwap = false;

        foreach ($state->currentMercPlays as $merc) {
            if ($merc && $merc->abilityType === MercenaryAbilityType::DiscardPlanetDrawNew) {
                $hasSwap = true;
                break;
            }
        }

        if (!$hasSwap) {
            return $state;
        }

        // Ensure there is a planet in the pot
        $state = $this->ensurePlanetInPot($state);

        if (!empty($state->planetPot) && $state->currentPlanetIndex < count($state->planetDeck)) {
            // Discard the most recently added planet from the pot
            array_pop($state->planetPot);

            // Draw a new one from the deck into the pot
            $state->planetPot[] = $state->planetDeck[$state->currentPlanetIndex];
            $state->currentPlanetIndex++;
        }

        return $state;
    }

    /**
     * Resolve the current battle.
     * - Compare played cards to determine winner.
     * - If a single winner: that player takes all planets in the pot.
     * - If tie: add another planet to pot (if available).
     * - If tie and NO planets remain: discard pot and end game.
     */
    private function resolveBattle(GameState $state): GameState
    {
        // Apply planet-affecting merc abilities first
        $state = $this->applyMercenaryPreBattleEffects($state);

        // Compute effective strengths per player
        $baseStrengths      = [];
        $effectiveStrengths = [];

        foreach ($state->currentPlays as $playerId => $value) {
            $baseStrengths[$playerId] = (int) $value;
        }

        // Start with base strengths as effective
        $effectiveStrengths = $baseStrengths;

        // Handle OverpowerFifteen
        foreach ($state->currentMercPlays as $playerId => $merc) {
            if (!$merc || $merc->abilityType !== MercenaryAbilityType::OverpowerFifteen) {
                continue;
            }

            // Check if any opponent has base strength 15
            $hasFifteenOpponent = false;
            foreach ($baseStrengths as $otherId => $strength) {
                if ($otherId === $playerId) {
                    continue;
                }
                if ($strength === 15) {
                    $hasFifteenOpponent = true;
                    break;
                }
            }

            if ($hasFifteenOpponent) {
                $effectiveStrengths[$playerId] = 16;
            } else {
                $fallback = $merc->params['fallback_strength'] ?? 1;
                $effectiveStrengths[$playerId] = (int) $fallback;
            }
        }

        // Determine winners based on effective strengths
        $max = max($effectiveStrengths);
        $winners = array_keys(array_filter(
            $effectiveStrengths,
            fn (int $v) => $v === $max
        ));

        // WinAllTies override: if there is a tie and exactly one of the tied players
        // used WinAllTies, that player wins outright.
        if (count($winners) > 1) {
            $tieBreakers = [];
            foreach ($winners as $playerId) {
                $merc = $state->currentMercPlays[$playerId] ?? null;
                if ($merc && $merc->abilityType === MercenaryAbilityType::WinAllTies) {
                    $tieBreakers[] = $playerId;
                }
            }

            if (count($tieBreakers) === 1) {
                $winners = $tieBreakers;
            }
        }

        // Clear current plays (ships already removed from hands earlier)
        $this->resetCurrentPlays($state);
        $this->resetCurrentMercPlays($state);

        if (count($winners) === 1) {
            $winnerId = $winners[0];

            foreach ($state->planetPot as $planet) {
                $state = $this->awardPlanetToPlayer($state, $winnerId, $planet);
            }

            $state->planetPot = [];
        } else {
            // tie; add another planet to the pot (if available)
            if ($state->currentPlanetIndex < count($state->planetDeck)) {
                $state->planetPot[] = $state->planetDeck[$state->currentPlanetIndex];
                $state->currentPlanetIndex++;
            } else {
                // tie and NO planets left; discard the pot and end game
                $state->planetPot = [];
                $state->gameOver = true;
                $state->endReason = GameEndReason::FinalTiePotDiscarded;
            }
        }

        return $state;
    }

    /**
     * Award a mercenary to a player after a successful bid.
     *
     * Rules:
     * - Winner discards the ship card they used to bid (removed from hand).
     * - Winner gains the mercenary (tracked in GameState::mercenaries).
     * The loser hand stays untouched.
     */
    public function awardMercenaryToPlayer(
        GameState $state,
        int $playerId,
        Mercenary $mercenary,
        int $bidCardValue
    ): GameState {
        if (!array_key_exists($playerId, $state->hands)) {
            throw new \InvalidArgumentException("Unknown player {$playerId}.");
        }

        // Ensure the player *currently* has this bid card available.
        if (!in_array($bidCardValue, $state->hands[$playerId], true)) {
            throw new \RuntimeException("Player {$playerId} does not have bid card {$bidCardValue}.");
        }

        // Remove the bid card from their hand (it's discarded).
        $state->hands[$playerId] = $this->removeFirst($state->hands[$playerId], $bidCardValue);

        // Add the mercenary to their collection.
        if (!isset($state->mercenaries[$playerId])) {
            $state->mercenaries[$playerId] = [];
        }
        $state->mercenaries[$playerId][] = $mercenary;

        return $state;
    }

    public function playMercenary(GameState $state, int $playerId, string $mercenaryId): GameState
    {
        if ($state->gameOver) {
            throw new \RuntimeException('Game is already over.');
        }

        if (!array_key_exists($playerId, $state->hands)) {
            throw new \InvalidArgumentException("Unknown player {$playerId}.");
        }

        // First player to act this battle triggers planet reveal if needed
        $state = $this->ensurePlanetInPot($state);

        // Find the mercenary on this player
        $mercenaryIndex = null;
        $mercenary = null;

        foreach ($state->mercenaries[$playerId] ?? [] as $index => $m) {
            if ($m->id === $mercenaryId) {
                $mercenaryIndex = $index;
                $mercenary = $m;
                break;
            }
        }

        if (!$mercenary) {
            throw new \RuntimeException("Player {$playerId} does not own mercenary {$mercenaryId}.");
        }

        // Remove merc from the player's pool (it's now being used this battle)
        unset($state->mercenaries[$playerId][$mercenaryIndex]);
        $state->mercenaries[$playerId] = array_values($state->mercenaries[$playerId]);

        // Record that this player used this merc this battle
        $state->currentMercPlays[$playerId] = $mercenary;

        // For allPlayersHavePlayed, we still need a numeric marker
        // – use baseStrength as the provisional value.
        $state->currentPlays[$playerId] = $mercenary->baseStrength;

        // If all players have acted, resolve the battle
        if ($this->allPlayersHavePlayed($state)) {
            $state = $this->resolveBattle($state);

            if (!$state->gameOver) {
                if ($state->anyPlayerOutOfCardsEarly()) {
                    $state->gameOver = true;
                    $state->endReason = GameEndReason::PlayerOutOfCardsEarly;
                } elseif ($state->allHandsEmpty()) {
                    $state->gameOver = true;

                    $hasUnclaimed = $state->unclaimedPlanets() !== [];
                    $state->endReason = $hasUnclaimed
                        ? GameEndReason::ShipsExhaustedPlanetsRemaining
                        : GameEndReason::Normal;
                }
            }
        }

        return $state;
    }

    /**
     * Base VP per class for a player, ignoring corporations and set bonuses.
     *
     * @return array<string, float> classValue|'none' => vp
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

        return $totals;
    }

    /**
     * Flat VP from set bonuses (ClassSetBonus abilities), NOT multiplied by corporations.
     */
    private function setBonusVpForPlayer(GameState $state, int $playerId): float
    {
        $planets = $state->claimedPlanets[$playerId] ?? [];

        if ($planets === []) {
            return 0.0;
        }

        $bonusTotal = 0.0;

        foreach ($planets as $planet) {
            foreach ($planet->abilities ?? [] as $ability) {
                if ($ability->type !== PlanetAbilityType::ClassSetBonus) {
                    continue;
                }

                $targetClassValue = $ability->params['class'] ?? null;
                $thresholds       = $ability->params['thresholds'] ?? [];

                if (!is_string($targetClassValue) || !is_array($thresholds)) {
                    continue;
                }

                // Count planets of that target class
                $count = 0;
                foreach ($planets as $p) {
                    if ($p->class?->value === $targetClassValue) {
                        $count++;
                    }
                }

                if ($count === 0) {
                    continue;
                }

                // Find highest threshold <= count
                ksort($thresholds);
                $bonus = 0.0;
                foreach ($thresholds as $threshold => $value) {
                    $threshold = (int) $threshold;
                    if ($count >= $threshold) {
                        $bonus = (float) $value;
                    }
                }

                $bonusTotal += $bonus;
            }
        }

        return $bonusTotal;
    }

    /**
     * Final scores with corporation multipliers AND set-bonus VP applied.
     *
     * - Per-class VP is multiplied by the player's corporation.
     * - Classless VP ("none") is added without multipliers.
     * - Set-bonus VP is computed separately and added at the end (not multiplied).
     *
     * @return array<int, float> playerId => score
     */
    public function finalScores(GameState $state): array
    {
        $scores = [];

        for ($playerId = 1; $playerId <= $state->playerCount; $playerId++) {
            $corp        = $state->corporations[$playerId] ?? null;
            $baseByClass = $this->baseVpByClassForPlayer($state, $playerId);

            $total = 0.0;

            foreach ($baseByClass as $classKey => $vp) {
                if ($classKey === 'none') {
                    // Classless VP is never multiplied
                    $total += $vp;
                    continue;
                }

                $class = PlanetClass::from($classKey);
                $mult  = $corp?->multiplierForClass($class) ?? 1.0;

                $total += $vp * $mult;
            }

            // Add set-bonus VP flat, AFTER multipliers
            $total += $this->setBonusVpForPlayer($state, $playerId);

            $scores[$playerId] = $total;
        }

        return $scores;
    }

    public function isGameOver(GameState $state): bool
    {
        return $state->gameOver;
    }
}
