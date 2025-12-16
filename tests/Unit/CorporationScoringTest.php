<?php

declare(strict_types=1);

use StellarSkirmish\Planet;
use StellarSkirmish\PlanetClass;
use StellarSkirmish\PlanetAbility;
use StellarSkirmish\PlanetAbilityType;
use StellarSkirmish\Corporation;
use StellarSkirmish\GameState;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameEndReason;

function makeGameStateForCorps(array $claimedPlanetsByPlayer, array $corporationsByPlayer): GameState
{
    $playerCount = count($claimedPlanetsByPlayer);

    // Minimal stub values for things we don’t care about in scoring
    $hands = [];
    $currentPlays = [];
    for ($p = 1; $p <= $playerCount; $p++) {
        $hands[$p] = [];
        $currentPlays[$p] = null;
    }

    return new GameState(
        playerCount: $playerCount,
        hands: $hands,
        planetDeck: [],            // not needed for scoring
        currentPlanetIndex: 0,
        planetPot: [],
        claimedPlanets: $claimedPlanetsByPlayer,
        currentPlays: $currentPlays,
        gameOver: true,
        endReason: GameEndReason::Normal,
        corporations: $corporationsByPlayer,
    );
}

it('computes final scores equal to raw scores when no corporation is assigned', function () {
    $engine = new GameEngine();

    $p1Mining = new Planet(
        id: 'M1',
        victoryPoints: 2,
        name: 'Mining World',
        planetClass: PlanetClass::MiningColony,
    );

    $p1Research = new Planet(
        id: 'R1',
        victoryPoints: 1,
        name: 'Research World',
        planetClass: PlanetClass::ResearchColony,
    );

    // Player 1: total raw VP = 3
    $claimed = [
        1 => [$p1Mining, $p1Research],
    ];

    $state = makeGameStateForCorps(
        claimedPlanetsByPlayer: $claimed,
        corporationsByPlayer: [1 => null],
    );

    $scores = $engine->finalScores($state);

    expect($scores[1])->toBe(3.0);
});

it('applies corporation multipliers per class to final scores', function () {
    $engine = new GameEngine();

    $mining = new Planet(
        id: 'M1',
        victoryPoints: 3,
        name: 'Mining World',
        planetClass: PlanetClass::MiningColony,
    );

    $trade = new Planet(
        id: 'T1',
        victoryPoints: 2,
        name: 'Trade Post',
        planetClass: PlanetClass::TradePostColony,
    );

    $research = new Planet(
        id: 'R1',
        victoryPoints: 1,
        name: 'Research Outpost',
        planetClass: PlanetClass::ResearchColony,
    );

    // Raw VP: 3 (Mining) + 2 (Trade) + 1 (Research) = 6
    // Corp: Mining x2.0, TradePost x-1.0, Research x1.0
    // Final: (3*2.0) + (2*-1.0) + (1*1.0) = 6 - 2 + 1 = 5
    $corp = new Corporation(
        id: 'CORP_TEST',
        name: 'Test Syndicate',
        classMultipliers: [
            PlanetClass::MiningColony->value      => 2.0,
            PlanetClass::TradePostColony->value   => -1.0,
            PlanetClass::ResearchColony->value    => 1.0,
        ],
    );

    $state = makeGameStateForCorps(
        claimedPlanetsByPlayer: [1 => [$mining, $trade, $research]],
        corporationsByPlayer: [1 => $corp],
    );
    dd($state);
    $scores = $engine->finalScores($state);
    dd($scores);
    expect($scores[1])->toBe(5.0);
});

it('supports fractional multipliers such as 1.5x for expansion corporations', function () {
    $engine = new GameEngine();

    // Two mining worlds, 2 VP each
    $m1 = new Planet(
        id: 'M1',
        victoryPoints: 2,
        name: 'Mining World 1',
        planetClass: PlanetClass::MiningColony,
    );

    $m2 = new Planet(
        id: 'M2',
        victoryPoints: 2,
        name: 'Mining World 2',
        planetClass: PlanetClass::MiningColony,
    );

    // Base VP for Mining = 4
    // Corp: Mining x1.5
    // Final = 4 * 1.5 = 6
    $corp = new Corporation(
        id: 'CORP_EXP',
        name: 'Expansion Consortium',
        classMultipliers: [
            PlanetClass::MiningColony->value => 1.5,
        ],
    );

    $state = makeGameStateForCorps(
        claimedPlanetsByPlayer: [1 => [$m1, $m2]],
        corporationsByPlayer: [1 => $corp],
    );

    $scores = $engine->finalScores($state);

    expect($scores[1])->toBe(6.0);
});

it('applies set-bonus VP after base scoring when no corporation is present', function () {
    $engine = new GameEngine();

    // Two mining worlds, 2 VP each => Mining base = 4
    $m1 = new Planet(
        id: 'M1',
        victoryPoints: 2,
        name: 'Mining World 1',
        planetClass: PlanetClass::MiningColony,
    );

    $m2 = new Planet(
        id: 'M2',
        victoryPoints: 2,
        name: 'Mining World 2',
        planetClass: PlanetClass::MiningColony,
    );

    // Station that grants a set bonus for Mining:
    // if player has >= 2 Mining worlds, gain +3 VP.
    $bonusStation = new Planet(
        id: 'S1',
        victoryPoints: 0, // base VP from the station itself
        name: 'Mining Syndicate HQ',
        planetClass: PlanetClass::Station,
        abilities: [
            new PlanetAbility(
                PlanetAbilityType::ClassSetBonus,
                [
                    'class' => PlanetClass::MiningColony->value,
                    'thresholds' => [
                        2 => 3, // 2 or more mining worlds => +3 VP flat
                    ],
                ]
            ),
        ],
    );

    $state = makeGameStateForCorps(
        claimedPlanetsByPlayer: [1 => [$m1, $m2, $bonusStation]],
        corporationsByPlayer: [1 => null],
    );

    // Expectation:
    // - Base VP = 2 + 2 + 0 = 4
    // - Set bonus = +3 (for having 2 mining worlds)
    // - Final = 4 + 3 = 7
    $scores = $engine->finalScores($state);

    expect($scores[1])->toBe(7.0);
});

it('does not multiply set-bonus VP by corporation multipliers', function () {
    $engine = new GameEngine();

    // Two mining worlds, 2 VP each => base Mining VP = 4
    $m1 = new Planet(
        id: 'M1',
        victoryPoints: 2,
        name: 'Mining World 1',
        planetClass: PlanetClass::MiningColony,
    );

    $m2 = new Planet(
        id: 'M2',
        victoryPoints: 2,
        name: 'Mining World 2',
        planetClass: PlanetClass::MiningColony,
    );

    // Same bonus station as above: +3 VP if >= 2 mining worlds
    $bonusStation = new Planet(
        id: 'S1',
        victoryPoints: 0,
        name: 'Mining Syndicate HQ',
        planetClass: PlanetClass::Station,
        abilities: [
            new PlanetAbility(
                PlanetAbilityType::ClassSetBonus,
                [
                    'class' => PlanetClass::MiningColony->value,
                    'thresholds' => [
                        2 => 3,
                    ],
                ]
            ),
        ],
    );

    // Corp: Mining x2.0
    $corp = new Corporation(
        id: 'CORP_MINING_X2',
        name: 'Deep Core Ventures',
        classMultipliers: [
            PlanetClass::MiningColony->value => 2.0,
        ],
    );

    $state = makeGameStateForCorps(
        claimedPlanetsByPlayer: [1 => [$m1, $m2, $bonusStation]],
        corporationsByPlayer: [1 => $corp],
    );

    // Expected scoring:
    // - Base VP by class:
    //   Mining: 2 + 2 = 4
    //   Station: 0
    // - Apply corp multipliers:
    //   Mining: 4 * 2.0 = 8
    // - Set bonus: +3 (for 2 mining worlds), NOT multiplied
    // Final = 8 + 3 = 11
    $scores = $engine->finalScores($state);

    expect($scores[1])->toBe(11.0);
});


