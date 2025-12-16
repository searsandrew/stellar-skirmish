<?php

declare(strict_types=1);

use StellarSkirmish\GameConfig;
use StellarSkirmish\GameEngine;
use StellarSkirmish\GameState;
use StellarSkirmish\GameEndReason;
use StellarSkirmish\Mercenary;
use StellarSkirmish\MercenaryAbilityType;

function makeSimpleStateForMercs(): GameState
{
    $engine = new GameEngine();
    $config = new GameConfig(
        playerCount: 2,
        planets: [],                 // planets not relevant for this test
        fleetValues: range(1, 15),   // standard 15-ship fleet
    );

    $state = $engine->startNewGame($config);

    // For these tests we don't care about planets or corporations, but we'll
    // make sure the mercenaries array is initialized.
    $state->mercenaries = [
        1 => [],
        2 => [],
    ];

    return $state;
}

it('serializes and restores mercenaries on game state', function () {
    $merc = new Mercenary(
        id: 'MERC_1',
        name: 'Shadow Runner',
        baseStrength: 7,
        abilityType: MercenaryAbilityType::WinAllTies,
        params: ['once_per_round' => true],
    );

    $state = new GameState(
        playerCount: 2,
        hands: [
            1 => range(1, 15),
            2 => range(1, 15),
        ],
        planetDeck: [],
        currentPlanetIndex: 0,
        planetPot: [],
        claimedPlanets: [
            1 => [],
            2 => [],
        ],
        currentPlays: [
            1 => null,
            2 => null,
        ],
        gameOver: false,
        endReason: null,
        corporations: [
            1 => null,
            2 => null,
        ],
        mercenaries: [
            1 => [$merc],
            2 => [],
        ],
    );

    $array    = $state->toArray();
    $restored = GameState::fromArray($array);

    expect($restored->mercenaries[1])->toHaveCount(1);
    expect($restored->mercenaries[2])->toHaveCount(0);

    $restoredMerc = $restored->mercenaries[1][0];

    expect($restoredMerc->id)->toBe('MERC_1');
    expect($restoredMerc->name)->toBe('Shadow Runner');
    expect($restoredMerc->baseStrength)->toBe(7);
    expect($restoredMerc->abilityType)->toBe(MercenaryAbilityType::WinAllTies);
    expect($restoredMerc->params['once_per_round'])->toBeTrue();
});

it('awards a mercenary to the winning player and discards their bid card', function () {
    $engine = new GameEngine();
    $state  = makeSimpleStateForMercs();

    // Player 1 will bid with card value 10
    $bidCard = 10;

    // Sanity: player 1 starts with 15 ships, including 10
    expect($state->hands[1])->toHaveCount(15);
    expect($state->hands[1])->toContain($bidCard);

    $merc = new Mercenary(
        id: 'MERC_2',
        name: 'Silent Blade',
        baseStrength: 9,
        abilityType: MercenaryAbilityType::OverpowerFifteen,
        params: ['fallback_strength' => 1],
    );

    $state = $engine->awardMercenaryToPlayer(
        $state,
        playerId: 1,
        mercenary: $merc,
        bidCardValue: $bidCard,
    );

    // Player 1's bid card should be gone from their hand
    expect($state->hands[1])->not->toContain($bidCard);

    // Player 1 should own the mercenary
    expect($state->mercenaries[1])->toHaveCount(1);
    $owned = $state->mercenaries[1][0];
    expect($owned->id)->toBe('MERC_2');

    // Player 2's hand is unchanged
    expect($state->hands[2])->toHaveCount(15);

    // Total "card-like things" for player 1 (ships + mercs) is still 15
    $total1 = count($state->hands[1]) + count($state->mercenaries[1]);
    expect($total1)->toBe(15);
});

it('throws if awarding a mercenary with a bid card the player does not have', function () {
    $engine = new GameEngine();
    $state  = makeSimpleStateForMercs();

    // Remove card 5 from player 1's hand
    $state->hands[1] = array_values(array_filter(
        $state->hands[1],
        fn (int $v) => $v !== 5
    ));

    $merc = new Mercenary(
        id: 'MERC_3',
        name: 'Ghost Fleet',
        baseStrength: 8,
        abilityType: MercenaryAbilityType::PeekNextPlanet,
    );

    $engine->awardMercenaryToPlayer(
        $state,
        playerId: 1,
        mercenary: $merc,
        bidCardValue: 5,
    );
})->throws(RuntimeException::class);
