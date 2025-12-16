<?php

declare(strict_types=1);

use StellarSkirmish\{
    GameEngine,
    GameConfig,
    GameState,
    Planet,
    PlanetClass,
    PlanetAbility,
    PlanetAbilityType,
    Corporation,
    Mercenary,
    MercenaryAbilityType,
};

it('plays a golden path game with planets, a corporation, and a mercenary', function () {
    // 1) Build a tiny, fully deterministic deck
    $trigger = new Planet(
        id: 'P1',
        victoryPoints: 1,
        name: 'Trigger World',
        planetClass: PlanetClass::TradePostColony,
        abilities: [
            new PlanetAbility(
                PlanetAbilityType::DoubleNextPlanetNoCombat,
                []
            ),
        ],
    );

    $mining = new Planet(
        id: 'P2',
        victoryPoints: 2,
        name: 'Rich Mining World',
        planetClass: PlanetClass::MiningColony,
    );

    $research = new Planet(
        id: 'P3',
        victoryPoints: 3,
        name: 'Research Hub',
        planetClass: PlanetClass::ResearchColony,
    );

    $config = new GameConfig(
        playerCount: 2,
        planets: [$trigger, $mining, $research],
        fleetValues: range(1, 15),
    );

    $engine = new GameEngine();
    $state  = $engine->startNewGame($config);

    // 2) Assign a corporation to player 1 (Mining x2)
    $miningCorp = new Corporation(
        id: 'CORP_MINING',
        name: 'Deep Core Ventures',
        classMultipliers: [
            PlanetClass::MiningColony->value => 2.0,
        ],
    );

    $state->corporations[1] = $miningCorp;

    // 3) Award a mercenary to player 1 by bidding a 5
    $merc = new Mercenary(
        id: 'MERC_OF',
        name: 'Overpower Ace',
        baseStrength: 7,
        abilityType: MercenaryAbilityType::OverpowerFifteen,
        params: ['fallback_strength' => 1],
    );

    // Ensure player 1 actually has card 5 before awarding
    expect($state->hands[1])->toContain(5);

    $state = $engine->awardMercenaryToPlayer(
        $state,
        playerId: 1,
        mercenary: $merc,
        bidCardValue: 5,
    );

    // Card 5 should be removed from player 1's hand; merc added
    expect($state->hands[1])->not->toContain(5);
    expect($state->mercenaries[1])->toHaveCount(1);

    // 4) Battle 1: player 1 wins Trigger World and doubles the next planet

    $state = $engine->playCard($state, 1, 10);
    $state = $engine->playCard($state, 2, 4);

    // Player 1 should now own P1 and P2, with P2 doubled
    expect($state->claimedPlanets[1])->toHaveCount(2);

    $claimedIds = array_map(
        fn (Planet $p) => $p->id,
        $state->claimedPlanets[1]
    );

    expect($claimedIds)->toContain('P1');
    expect($claimedIds)->toContain('P2');

    // Check VP values: P1 = 1, P2 doubled from 2 -> 4
    $vpById = [];
    foreach ($state->claimedPlanets[1] as $planet) {
        $vpById[$planet->id] = $planet->victoryPoints;
    }

    expect($vpById['P1'])->toBe(1);
    expect($vpById['P2'])->toBe(4);

    // 5) Battle 2: player 1 uses the merc vs a 15 to win P3

    // Merc still present
    expect($state->mercenaries[1])->toHaveCount(1);

    $state = $engine->playMercenary($state, 1, 'MERC_OF');
    $state = $engine->playCard($state, 2, 15);

    // After using the merc, it should be removed from the mercenaries list
    expect($state->mercenaries[1])->toHaveCount(0);

    // Player 1 should now own P3 as well
    expect($state->claimedPlanets[1])->toHaveCount(3);

    $claimedIds = array_map(
        fn (Planet $p) => $p->id,
        $state->claimedPlanets[1]
    );

    expect($claimedIds)->toContain('P3');

    // 6) Final scores: Mining is doubled by the corporation

    $scores = $engine->finalScores($state);

    // P1: P1 = 1 (TradePost), P2 = 4 (Mining, doubled by ability), P3 = 3 (Research)
    // MiningCorp: Mining x2.0
    //
    // Mining: 4 * 2.0 = 8
    // TradePost: 1
    // Research: 3
    // Total = 12
    expect($scores[1])->toBe(12.0);

    // Player 2 has claimed nothing
    expect($scores[2])->toBe(0.0);
});
