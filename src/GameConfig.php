<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class GameConfig
{
  public function __construct(
    public int $playerCount = 2,
    /** @var Planet[] */
    public array $planets = [],
    /** @var int[] */
    public array $fleetValues = [],
  ) {
    if ($this->playerCount < 2) {
      throw new \InvalidArgumentException('At least two players are required.');
    }

    if ($this->planets === []) {
      $this->planets = Planet::defaultDeck();
    }

    if ($this->fleetValues === []) {
      $this->fleetValues = range(1, 15);
    }
  }

  public static function standardTwoPlayer(): self
  {
    $planets = Planet::defaultDeck();
    shuffle($planets);

    return new self(
      playerCount: 2,
      planets: $planets,
      fleetValues: range(1, 15),
    );
  }
}
