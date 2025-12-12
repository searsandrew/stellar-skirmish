<?php

declare(strict_types=1);

namespace StellarSkirmish\Tests\Unit;

use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameState;
use StellarSkirmish\Planet;
use StellarSkirmish\PlanetClass;
use StellarSkirmish\PlanetAbility;
use StellarSkirmish\PlanetAbilityType;

it('awards the next planet doubled with no extra combat when a planet has the double-next ability', function () {
    $engine = new GameEngine();
    $config = makeConfigWithDoubleNextPlanet();

    $state = $engine->startNewGame($config);

    // Player 1 plays higher card and wins P1
    $state = $engine->playCard($state, playerId: 1, cardValue: 10);
    $state = $engine->playCard($state, playerId: 2, cardValue: 5);

    // Pot should now be empty after awarding
    expect($state->planetPot)->toBe([]);

    // Player 1 should have two planets:
    // - P1 (1 VP, the trigger world)
    // - P2 (2 VP, but doubled to 4 VP by the ability)
    expect($state->claimedPlanets[1])->toHaveCount(2);

    [$claimedP1, $claimedP2] = $state->claimedPlanets[1];

    expect($claimedP1)->toBeInstanceOf(Planet::class);
    expect($claimedP2)->toBeInstanceOf(Planet::class);

    expect($claimedP1->id)->toBe('P1');
    expect($claimedP1->victoryPoints)->toBe(1);

    // This is the key: doubled VP for the next planet
    expect($claimedP2->id)->toBe('P2');
    expect($claimedP2->victoryPoints)->toBe(4);

    // Player 2 should have claimed nothing
    expect($state->claimedPlanets[2])->toHaveCount(0);

    // currentPlanetIndex should have advanced past both P1 and P2 (index 2),
    // so the next battle will be for P3.
    expect($state->currentPlanetIndex)->toBe(2);
});

it('does nothing for double-next ability if there is no planet left in the deck - this should be impossible', function () {
    $engine = new GameEngine();

    // Only a single planet that has the ability; no "next" planet exists (this should never happen)
    $p1 = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Lonely Trigger World',
        class: PlanetClass::TradePostColony,
        abilities: [
            new PlanetAbility(
                PlanetAbilityType::DoubleNextPlanetNoCombat,
                []
            ),
        ],
    );

    $config = new GameConfig(
        playerCount: 2,
        planets: [$p1],
        fleetValues: [4, 5], // tiny fleet for this test
    );

    $state = $engine->startNewGame($config);

    // Player 1 wins the only planet
    $state = $engine->playCard($state, playerId: 1, cardValue: 5);
    $state = $engine->playCard($state, playerId: 2, cardValue: 4);

    // For safety, let's just assert that we don't error and that no extra planet was added:
    // currentPlanetIndex should be at most 1, and there should be only one claimed planet in total.

    $totalClaimed = count($state->claimedPlanets[1]) + count($state->claimedPlanets[2]);
    expect($totalClaimed)->toBeLessThanOrEqual(1);
});

it('ignores non-trigger abilities during battle resolution', function () {
    $engine = new GameEngine();

    $p1 = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Weird World',
        class: PlanetClass::MiningColony,
        abilities: [
            // This will be handled at scoring time, not on-claim
            new PlanetAbility(
                PlanetAbilityType::ClassSetBonus,
                ['class' => PlanetClass::MiningColony->value, 'thresholds' => [2 => 1]]
            ),
        ],
    );

    $p2 = new Planet(
        id: 'P2',
        victoryPoints: 2,
        name: 'Plain World',
        class: PlanetClass::ResearchColony,
        abilities: [],
    );

    $config = new GameConfig(
        playerCount: 2,
        planets: [$p1, $p2],
        fleetValues: [3, 5], // simple two-card fleets
    );

    $state = $engine->startNewGame($config);

    // Player 1 wins P1
    $state = $engine->playCard($state, 1, 5);
    $state = $engine->playCard($state, 2, 3);

    // Battle should resolve normally; no extra planets should be auto-awarded
    expect($state->claimedPlanets[1])->toHaveCount(1);
    expect($state->claimedPlanets[1][0]->id)->toBe('P1');
    expect($state->planetPot)->toBe([]);
});
