<?php

declare(strict_types=1);

use StellarSkirmish\Planet;
use StellarSkirmish\PlanetClass;
use StellarSkirmish\PlanetAbility;
use StellarSkirmish\PlanetAbilityType;

it('serializes and restores planets with classes and abilities', function () {
    $planet = new Planet(
        id: 'P42',
        victoryPoints: 2,
        name: 'The Ultimate Answer Outpost',
        planetClass: PlanetClass::IndustrialWorld,
        abilities: [
            new PlanetAbility(
                PlanetAbilityType::ClassSetBonus,
                [
                    'planetClass' => PlanetClass::ResearchColony->value,
                    'thresholds' => [
                        2 => 1,
                        3 => 3,
                        4 => 5,
                    ],
                ]
            ),
        ],
    );


    $array    = $planet->toArray();
    $restored = Planet::fromArray($array);

    expect($restored->id)->toBe('P42');
    expect($restored->victoryPoints)->toBe(2);
    expect($restored->name)->toBe('The Ultimate Answer Outpost');
    expect($restored->planetClass)->toBe(PlanetClass::IndustrialWorld);

    expect($restored->abilities)->toHaveCount(1);
    $ability = $restored->abilities[0];

    expect($ability->type)->toBe(PlanetAbilityType::ClassSetBonus);
    expect($ability->params['planetClass'])->toBe(PlanetClass::ResearchColony->value);
    expect($ability->params['thresholds'])->toMatchArray([
        2 => 1,
        3 => 3,
        4 => 5,
    ]);
});

it('default deck planets have valid classes', function () {
    $deck = Planet::defaultDeck();

    expect($deck)->not->toBeEmpty();

    foreach ($deck as $planet) {
        expect($planet->planetClass)->not()->toBeNull();

        // Will throw if the stored value is invalid
        PlanetClass::from($planet->planetClass->value);
    }
});
