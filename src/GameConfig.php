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
    public ?int $seed = null,
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

    if ($this->seed !== null) {
        $this->planets = self::shuffleWithSeed($this->planets, $this->seed);
    }
  }

    /**
     * Deterministic shuffle based on a simple integer seed.
     * @todo: replace with fancier seeding later
     *
     * @param Planet[] $planets
     * @return Planet[]
     */
    private static function shuffleWithSeed(array $planets, int $seed): array
    {
        mt_srand($seed);
        shuffle($planets);
        mt_srand();

        return $planets;
    }

    public static function standardTwoPlayer(?int $seed = null): self
    {
        return new self(
            playerCount: 2,
            planets: Planet::defaultDeck(),
            fleetValues: range(1, 15),
            seed: $seed,
        );
    }
}
