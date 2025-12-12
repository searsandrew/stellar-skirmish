<?php

declare(strict_types=1);

use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameState;
use StellarSkirmish\Planet;
use StellarSkirmish\PlanetClass;
use StellarSkirmish\Mercenary;
use StellarSkirmish\MercenaryAbilityType;
use StellarSkirmish\GameEndReason;

function makeMercBattleConfig(Planet $planet): GameConfig
{
    return new GameConfig(
        playerCount: 2,
        planets: [$planet],
        fleetValues: range(1, 15),
    );
}

it('mercenary with OverpowerFifteen counts as 16 when an opponent plays 15', function () {
    $engine = new GameEngine();

    $planet = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Battle World',
        class: PlanetClass::MiningColony,
    );

    $config = makeMercBattleConfig($planet);
    $state  = $engine->startNewGame($config);

    $merc = new Mercenary(
        id: 'M_OF',
        name: 'Overpower Ace',
        baseStrength: 7,
        abilityType: MercenaryAbilityType::OverpowerFifteen,
        params: ['fallback_strength' => 1],
    );

    // player 1 owns the merc
    $state->mercenaries[1] = [$merc];

    // player 1 plays the merc, player 2 plays a 15
    $state = $engine->playMercenary($state, 1, 'M_OF');
    $state = $engine->playCard($state, 2, 15);

    // player 1 should win the planet (effective 16 vs 15)
    expect($state->claimedPlanets[1])->toHaveCount(1);
    expect($state->claimedPlanets[1][0]->id)->toBe('P1');
    expect($state->claimedPlanets[2])->toBeEmpty();

    // planet pot should be empty after awarding
    expect($state->planetPot)->toBe([]);
});

it('mercenary with OverpowerFifteen uses fallback strength when no opponent plays 15', function () {
    $engine = new GameEngine();

    $planet = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Battle World',
        class: PlanetClass::MiningColony,
    );

    $config = makeMercBattleConfig($planet);
    $state  = $engine->startNewGame($config);

    $merc = new Mercenary(
        id: 'M_OF',
        name: 'Overpower Ace',
        baseStrength: 7,
        abilityType: MercenaryAbilityType::OverpowerFifteen,
        params: ['fallback_strength' => 1],
    );

    $state->mercenaries[1] = [$merc];

    // player 1 plays the merc, player 2 plays a 3
    $state = $engine->playMercenary($state, 1, 'M_OF');
    $state = $engine->playCard($state, 2, 3);

    // fallback strength is 1, so player 2 should win with 3
    expect($state->claimedPlanets[2])->toHaveCount(1);
    expect($state->claimedPlanets[2][0]->id)->toBe('P1');
    expect($state->claimedPlanets[1])->toBeEmpty();
});

it('mercenary with WinAllTies wins a tie when only one player uses it', function () {
    $engine = new GameEngine();

    $planet = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Tie World',
        class: PlanetClass::TradePostColony,
    );

    $config = makeMercBattleConfig($planet);
    $state  = $engine->startNewGame($config);

    $merc = new Mercenary(
        id: 'M_TIE',
        name: 'Tie Breaker',
        baseStrength: 5,
        abilityType: MercenaryAbilityType::WinAllTies,
    );

    $state->mercenaries[1] = [$merc];

    // Both sides effectively play 5, but player 1 uses WinAllTies
    $state = $engine->playMercenary($state, 1, 'M_TIE');
    $state = $engine->playCard($state, 2, 5);

    expect($state->claimedPlanets[1])->toHaveCount(1);
    expect($state->claimedPlanets[1][0]->id)->toBe('P1');
    expect($state->claimedPlanets[2])->toBeEmpty();
});

it('mercenary WinAllTies is ignored when multiple players use it in the same tie', function () {
    $engine = new GameEngine();

    $p1 = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'First World',
        class: PlanetClass::TradePostColony,
    );

    $p2 = new Planet(
        id: 'P2',
        victoryPoints: 2,
        name: 'Second World',
        class: PlanetClass::ResearchColony,
    );

    $config = new GameConfig(
        playerCount: 2,
        planets: [$p1, $p2],
        fleetValues: range(1, 15),
    );

    $state = $engine->startNewGame($config);

    $merc1 = new Mercenary(
        id: 'M_TIE_1',
        name: 'Tie Breaker A',
        baseStrength: 5,
        abilityType: MercenaryAbilityType::WinAllTies,
    );

    $merc2 = new Mercenary(
        id: 'M_TIE_2',
        name: 'Tie Breaker B',
        baseStrength: 5,
        abilityType: MercenaryAbilityType::WinAllTies,
    );

    $state->mercenaries[1] = [$merc1];
    $state->mercenaries[2] = [$merc2];

    // Both use WinAllTies at strength 5 => tie should be resolved normally
    $state = $engine->playMercenary($state, 1, 'M_TIE_1');
    $state = $engine->playMercenary($state, 2, 'M_TIE_2');

    // With two planets in the deck and a tie:
    // - P1 is in the pot
    // - P2 is added to the pot on tie
    // - No one should have claimed anything yet
    expect($state->claimedPlanets[1])->toBeEmpty();
    expect($state->claimedPlanets[2])->toBeEmpty();
    expect($state->planetPot)->toHaveCount(2);
});

it('mercenary with DiscardPlanetDrawNew replaces the contested planet before resolution', function () {
    $engine = new GameEngine();

    $p1 = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Unlucky World',
        class: PlanetClass::TradePostColony,
    );

    $p2 = new Planet(
        id: 'P2',
        victoryPoints: 3,
        name: 'Lucky World',
        class: PlanetClass::MiningColony,
    );

    $config = new GameConfig(
        playerCount: 2,
        planets: [$p1, $p2],
        fleetValues: range(1, 15),
    );

    $state = $engine->startNewGame($config);

    $merc = new Mercenary(
        id: 'M_SWAP',
        name: 'Planet Switcher',
        baseStrength: 10,
        abilityType: MercenaryAbilityType::DiscardPlanetDrawNew,
    );

    $state->mercenaries[1] = [$merc];

    // Player 1 uses the swap merc; player 2 plays something lower so player 1 wins.
    $state = $engine->playMercenary($state, 1, 'M_SWAP');
    $state = $engine->playCard($state, 2, 3);

    // Player 1 should have won P2, not P1
    expect($state->claimedPlanets[1])->toHaveCount(1);
    expect($state->claimedPlanets[1][0]->id)->toBe('P2');

    // P1 should not be claimed by anyone
    $allClaimedIds = array_map(
        fn (Planet $p) => $p->id,
        array_merge($state->claimedPlanets[1], $state->claimedPlanets[2])
    );

    expect($allClaimedIds)->not->toContain('P1');
});

