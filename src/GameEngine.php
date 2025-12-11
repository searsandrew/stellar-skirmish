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

        if ($state->currentPlanetIndex >= count($state->planetDeck)) {
            return $state;
        }

        $state->planetPot[] = $state->planetDeck[$state->currentPlanetIndex];
        $state->currentPlanetIndex++;

        return $state;
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
        }

        // once all hands are empty, game is over
        if ($state->allHandsEmpty()) {
            $state->gameOver = true;
        }

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
     * - If a single winner: that player takes all planets in the pot
     * - If tie: add another planet to pot (if available), leave the pot as-is, and a new battle begins, with a planet added to the pot.
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

            // award all planets currently in the pot to winner
            foreach ($state->planetPot as $planet) {
                $state->claimedPlanets[$winnerId][] = $planet;
            }

            $state->planetPot = [];
        } else {
            // tie; add another planet to the pot (if available)
            if ($state->currentPlanetIndex < count($state->planetDeck)) {
                $state->planetPot[] = $state->planetDeck[$state->currentPlanetIndex];
                $state->currentPlanetIndex++;
            }

            // @todo end the game if there are no more planets left
        }

        return $state;
    }

    public function isGameOver(GameState $state): bool
    {
        return $state->gameOver;
    }
}
