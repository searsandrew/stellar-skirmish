<?php

namespace StellarSkirmish;

final class GameState
{
  /** @var array<int, array<int>> playerId => ship card values in hand */
  public array $hands = [];

  /** @var array<int> */
  public array $planetDeck = [];

  /** @var array<int, array<int>> playerId => planet vistory points */
  public array $claimedPlanets = [];

  /** @var array<int, int|null> playerId => last played card */
  public array $currentPlays = [];

  /** @var array<int> stack of planet cards at stake (for ties) */
  public array $planetPot = [];

  public int $currentPlanetIndex = 0;

  public function __construct(
    array $hands,
    array $planetDeck,
    array $claimedPlanets,
    array $currentPlays,
    array $planetPot,
    int $currentPlanetIndex
  ) {
    $this->hands		= $hands;
    $this->planetDeck		= $planetDeck;
    $this->claimedPlanets	= $claimedPlanets;
    $this->currentPlays		= $currentPlays;
    $this->planetPot		= $planetPot;
    $this->currentPlanetIndex	= $currentPlanetIndex;
  }
}
