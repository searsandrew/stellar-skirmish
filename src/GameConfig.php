<?php

namespace StellarSkirmish;

final class GameConfig
{
  public int $players;

  public function __construct(int $players = 2)
  {
    $this->players = $players;
  }
}
