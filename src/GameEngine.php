<?php

declare(strict_types=1);

namespace StellarSkirmish;

use StellarSkirmish\Exceptions\GameOver;
use StellarSkirmish\Exceptions\InvalidMove;

final class GameEngine
{
    public function startNewGame(GameConfig $config): GameState
    {
        $hands = [];
        $claimed = [];
        $plays = [];

        for ($p = 1; $p <= $config->playerCount; $p++) {
            $hands[$p] = array_values($config->fleetValues);
            $claimed[$p] = [];
            $plays[$p] = null;
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
            class: $nextPlanet->class,
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

    /**
     * Resolve the current battle.
     * - Compare played cards to determine winner.
     * - If a single winner: that player takes all planets in the pot.
     * - If tie: add another planet to pot (if available).
     * - If tie and NO planets remain: discard pot and end game.
     */
    private function resolveBattle(GameState $state): GameState
    {
        $plays = $state->currentPlays;

        // highest card value
        $max = max($plays);
        $winners = array_keys(array_filter(
            $plays,
            fn (int $v) => $v === $max
        ));

        $this->resetCurrentPlays($state);

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

    public function isGameOver(GameState $state): bool
    {
        return $state->gameOver;
    }
}
