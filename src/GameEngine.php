<?php

namespace StellarSkirmish;

use StellarSkirmish\Exceptions\InvalidMove;

final class GameEngine
{
  public function startNewGame(GameConfig $config): GameState
  {
    $hands = [];
    for ($p = 1; $p <= $config->players; $p++) {
      $hands[$p] = range(1, 15);
    }

    // @todo Build actual randomizer for deck
    $planetDeck = [1, 2, 2, 3, 1, 3, 2, 1, 3, 2, 2, 1, 3, 1, 3];

    $claimedPlanets = [];
    for ($p = 1; $p <= $config->players; $p++;) {
      $claimedPlanets[$p] = [];
    }

    $currentPlays = array_fill(1, $config->players, null);

    return new GameState(
	hands:		$hands,
	planetDeck:	$planetDeck,
	claimedPlanets:	$claimedPlanets,
	currentPlays:	$currentPlays,
	planetPot:	[],
	currentPlanetIndex: 0,
    );
  }

  public function playCard(GameState $state, int $playerId, int $cardValue): GameState
  {
    if (!in_array($cardValue, $state->hands[$playerId] ?? [], true)) {
      throw new InvalidMove("Player {$playerId} does not have card {$cardValue}.");
    }

    // remove card from hand
    $state->hands[$playerId] = array_values(
      array_filter(
        $state->hands[$playerId],
        fn (int $c) => $c !== $cardValue
      )
    );

    $state->currentPlays[$playerId] = $cardValue;

    // if all players have played, resolve the round
    $allPlayed = !in_array(null, $state->currentPlays, true);

    if ($allPlayed) {
      $state = $this->resolveRound($state);
    }

    return $state;
  }

  private function resolveRound(GameState $state): GameState
  {
    // Figure out who won or if tie, and update $state
    // @todo implement planet pot logic
    $plays = $state->currentPlays;
    $max   = max($plays);
    $winners = array_keys(array_filter($plays, fn ($v) => $v === $max));

    // add current planet to pot
    $state->planetPot[] = $state->planetDeck[$state->currentPlanetIndex] ?? null;

    if (count($winners) === 1) {
      $winnerId = $winners[0];

      // award planets in pot
      foreach ($state->planetPot as $vp) {
        if ($vp !== null) {
          $state->claimedPlanets[$winnerId][] = $vp;
        }
      }

      $state->planetPot = [];
      $state->currentPlanetIndex++;
    } else {
      // tie: advance to next planet and maintain pot
      $state->currentPlanetIndex++;
    }

    // reset current plays
    foreach ($state->currentPlays as $pId => $_) {
      $state->currentPlays[$pId] = null;
    }

    return $state;
  }

  public function isGameOver(GameState $state): bool
  {
    // game ends when all ship cards are used
    return empty(array_filter($state->hands));
  }
}
