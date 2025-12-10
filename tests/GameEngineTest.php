<?php

use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;

if('starts a new game with two players and fifteen cards each', function() {
  $engine = new GameEngine();
  $state  = $engine->startNewGame(new GameConfig(players: 2));

  expect($state->hands[1])->toHaveCount(15);
  expect($state->hands[2])->toHaveCount(15);
  expect($state->currentPlanetIndex)->toBe(0);
});

it('lets a player play a card', function() {
  $engine = new GameEngine();
  $state  = $engine->startNewGame(new GameConfig());

  $state2 = $engine->playCard($state, playerId: 1, cardValue: 5);

  expect($state2->hands[1])->not->toContain(5);
});
