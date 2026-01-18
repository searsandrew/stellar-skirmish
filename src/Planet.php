<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class Planet
{
    public function __construct(
        public readonly string $id,
        public readonly int $victoryPoints,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?PlanetClass $planetClass = null,
        public readonly ?string $imageLink = null,
        /** @var PlanetAbility[] */
        public readonly array $abilities = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            victoryPoints: (int) $data['victory_points'],
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            planetClass: isset($data['class']) && $data['class'] !== null
                ? PlanetClass::from($data['class'])
                : null,
            imageLink: $data['image_link'] ?? null,
            abilities: array_map(
                fn (array $ability) => PlanetAbility::fromArray($ability),
                $data['abilities'] ?? []
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'victory_points' => $this->victoryPoints,
            'name'           => $this->name,
            'description'    => $this->description,
            'class'          => $this->planetClass?->value,
            'image_link'     => $this->imageLink,
            'abilities'      => array_map(
                fn (PlanetAbility $ability) => $ability->toArray(),
                $this->abilities
            ),
        ];
    }

    /**
     * A simple default deck you can replace later.
     *
     * 15 planets total:
     * - 5× TradePostColony  (1,1,2,2,3 VP)
     * - 5× ResearchColony   (1,1,2,2,3 VP)
     * - 5× MiningColony     (1,1,2,2,3 VP)
     *
     * @return Planet[]
     */
    public static function defaultDeck(?int $seed = null): array
    {
        $planets = [];
        $id      = 1;

        // VP pattern per class
        $vpPattern = [1, 1, 2, 2, 3];

        // Base-game planet classes included in the default deck
        $classes = [
            PlanetClass::TradePostColony,
            PlanetClass::ResearchColony,
            PlanetClass::MiningColony,
        ];

        $names = [
            PlanetClass::TradePostColony->value => ["Bazaar IV", "Neon Nexus", "Merchant's Haven", "Silk Road Station", "Trade Winds"],
            PlanetClass::ResearchColony->value  => ["Alpha Laboratory", "Knowledge Spire", "Quantum Observatory", "Data Core", "Mind's Eye"],
            PlanetClass::MiningColony->value    => ["Iron Rock", "Diamond Depths", "Methane Plains", "Ore Outpost", "The Quarry"],
        ];

        $descriptions = [
            PlanetClass::TradePostColony->value => "A bustling hub of galactic commerce where credits flow like water.",
            PlanetClass::ResearchColony->value  => "A quiet world dedicated to uncovering the secrets of the universe.",
            PlanetClass::MiningColony->value    => "A rugged planet rich in rare minerals and heavy metals.",
        ];

        // Build (class, vp, name, description) tuples
        $dataEntries = [];

        foreach ($classes as $planetClass) {
            $classNames = $names[$planetClass->value];
            foreach ($vpPattern as $i => $vp) {
                $dataEntries[] = [
                    'class' => $planetClass,
                    'vp'    => $vp,
                    'name'  => $classNames[$i],
                    'desc'  => $descriptions[$planetClass->value],
                ];
            }
        }

        // Optional deterministic shuffle
        if ($seed !== null) {
            mt_srand($seed);
        }

        shuffle($dataEntries);

        if ($seed !== null) {
            mt_srand(); // reset RNG to normal
        }

        // Build Planet instances with a non-null planetClass
        foreach ($dataEntries as $entry) {
            /** @var PlanetClass $pClass */
            $pClass = $entry['class'];
            $name   = $entry['name'];

            $planets[] = new Planet(
                id: 'P' . $id,
                victoryPoints: $entry['vp'],
                name: $name,
                description: $entry['desc'],
                planetClass: $pClass,
                imageLink: "https://images.stellar-skirmish.com/planets/p{$id}.png",
                abilities: [],
            );

            $id++;
        }

        return $planets;
    }
}
