<?php

declare(strict_types=1);

use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameState;
use StellarSkirmish\Planet;
use StellarSkirmish\Exceptions\InvalidMove;
use StellarSkirmish\Exceptions\GameOver;

function makeTestConfig(): GameConfig
{
    // Deterministic planets for tests: P1=1 VP, P2=2 VP, P3=3 VP
    $planets = [
        new Planet('P1', 1, 'Test Planet 1'),
        new Planet('P2', 2, 'Test Planet 2'),
        new Planet('P3', 3, 'Test Planet 3'),
    ];

    return new GameConfig(
        playerCount: 2,
        planets: $planets,
        fleetValues: range(1, 15),
    );
}

it('starts a game with correct initial state', function () {
    $engine = new GameEngine();
    $config = makeTestConfig();

    $state = $engine->startNewGame($config);

    expect($state->playerCount)->toBe(2);

    // Each player has cards 1..15
    expect($state->hands[1])->toBe(range(1, 15));
    expect($state->hands[2])->toBe(range(1, 15));

    // No planets in pot yet
    expect($state->planetPot)->toBe([]);

    // No planets claimed yet
    expect($state->claimedPlanets[1])->toBe([]);
    expect($state->claimedPlanets[2])->toBe([]);

    // Current planet index at start of deck
    expect($state->currentPlanetIndex)->toBe(0);

    // Deck is the planets from config
    expect($state->planetDeck)->toHaveCount(3);
    expect($state->planetDeck[0]->id)->toBe('P1');
    expect($state->planetDeck[1]->id)->toBe('P2');
});

it('resolves a simple non-tie battle correctly', function () {
    $engine = new GameEngine();
    $config = makeTestConfig();
    $state  = $engine->startNewGame($config);

    // Player 1 plays 5, player 2 plays 3
    $state = $engine->playCard($state, playerId: 1, cardValue: 5);
    $state = $engine->playCard($state, playerId: 2, cardValue: 3);

    // First planet should have been added to the pot and then claimed by winner
    // Winner is player 1 (5 > 3)
    expect($state->claimedPlanets[1])->toHaveCount(1);
    expect($state->claimedPlanets[1][0]->id)->toBe('P1');

    // Player 2 has none
    expect($state->claimedPlanets[2])->toHaveCount(0);

    // Pot is empty after awarding
    expect($state->planetPot)->toBe([]);

    // Both played cards are gone from hands
    expect($state->hands[1])->not->toContain(5);
    expect($state->hands[2])->not->toContain(3);
});

it('handles a tie by adding another planet to the pot and resolving on next battle', function () {
    $engine = new GameEngine();
    $config = makeTestConfig();
    $state  = $engine->startNewGame($config);

    // First battle: tie (5 vs 5)
    $state = $engine->playCard($state, playerId: 1, cardValue: 5);
    $state = $engine->playCard($state, playerId: 2, cardValue: 5);

    // There was a tie: pot should contain first planet (P1) and then P2 added on tie
    expect($state->planetPot)->toHaveCount(2);
    expect($state->planetPot[0]->id)->toBe('P1');
    expect($state->planetPot[1]->id)->toBe('P2');

    // No one has claimed planets yet
    expect($state->claimedPlanets[1])->toHaveCount(0);
    expect($state->claimedPlanets[2])->toHaveCount(0);

    // Hands should no longer contain the 5s
    expect($state->hands[1])->not->toContain(5);
    expect($state->hands[2])->not->toContain(5);

    // Second battle: player 1 wins (2 vs 1)
    $state = $engine->playCard($state, playerId: 1, cardValue: 2);
    $state = $engine->playCard($state, playerId: 2, cardValue: 1);

    // Player 1 takes entire pot (P1 + P2)
    expect($state->claimedPlanets[1])->toHaveCount(2);
    expect($state->claimedPlanets[1][0]->id)->toBe('P1');
    expect($state->claimedPlanets[1][1]->id)->toBe('P2');

    // Pot should now be empty
    expect($state->planetPot)->toHaveCount(0);
});

it('marks the game over when all hands are empty', function () {
    $engine = new GameEngine();

    // Tiny config: each player gets only card [1], and only one planet
    $planets = [new Planet('P1', 1)];
    $config  = new GameConfig(
        playerCount: 2,
        planets: $planets,
        fleetValues: [1],
    );

    $state = $engine->startNewGame($config);

    // Both players play their only card
    $state = $engine->playCard($state, playerId: 1, cardValue: 1);
    $state = $engine->playCard($state, playerId: 2, cardValue: 1);

    expect($state->allHandsEmpty())->toBeTrue();
    expect($state->gameOver)->toBeTrue();
    expect($engine->isGameOver($state))->toBeTrue();
});

it('throws InvalidMove if player tries to play a card they do not have', function () {
    $engine = new GameEngine();
    $config = makeTestConfig();
    $state  = $engine->startNewGame($config);

    // Remove card 10 from player 1 manually
    $state->hands[1] = array_filter($state->hands[1], fn (int $v) => $v !== 10);

    $engine->playCard($state, playerId: 1, cardValue: 10);
})->throws(InvalidMove::class);

it('can be serialized to array and restored from array', function () {
    $engine = new GameEngine();
    $config = makeTestConfig();
    $state  = $engine->startNewGame($config);

    // Play a couple of cards to mutate state
    $state = $engine->playCard($state, playerId: 1, cardValue: 5);
    $state = $engine->playCard($state, playerId: 2, cardValue: 3);

    $array = $state->toArray();
    $restored = GameState::fromArray($array);

    // Spot-check key fields
    expect($restored->playerCount)->toBe($state->playerCount);
    expect($restored->hands)->toBe($state->hands);
    expect($restored->planetPot)->toHaveCount($state->planetPot ? count($state->planetPot) : 0);
    expect($restored->claimedPlanets[1])->toHaveCount(count($state->claimedPlanets[1]));
    expect($restored->gameOver)->toBe($state->gameOver);
});
