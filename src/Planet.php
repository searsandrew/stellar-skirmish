<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class Planet
{
    public function __construct(
        public readonly string $id,
        public readonly int $victoryPoints,
        public readonly ?string $name = null,
        public readonly ?PlanetClass $planetClass = null,
        /** @var PlanetAbility[] */
        public readonly array $abilities = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            victoryPoints: (int) $data['victory_points'],
            name: $data['name'] ?? null,
            planetClass: isset($data['class']) && $data['class'] !== null
                ? PlanetClass::from($data['class'])
                : null,
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
            'class'          => $this->planetClass?->value,
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

        // Build (class, vp) tuples
        $tuples = [];

        foreach ($classes as $planetClass) {
            foreach ($vpPattern as $vp) {
                $tuples[] = [$planetClass, $vp];
            }
        }

        // Optional deterministic shuffle
        if ($seed !== null) {
            mt_srand($seed);
        }

        shuffle($tuples);

        if ($seed !== null) {
            mt_srand(); // reset RNG to normal
        }

        // Build Planet instances with a non-null planetClass
        foreach ($tuples as [$planetClass, $vp]) {
            /** @var PlanetClass $planetClass */
            $planets[] = new Planet(
                id: 'P' . $id,
                victoryPoints: $vp,
                name: "Planet {$id}",
                planetClass: $planetClass,
                abilities: [],
            );

            $id++;
        }

        return $planets;
    }
}
