<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class Planet
{
    public function __construct(
        public readonly string $id,
        public readonly int $victoryPoints,
        public readonly ?string $name = null,
        public readonly ?PlanetClass $class = null,
        /** @var PlanetAbility[] */
        public readonly array $abilities = [],
    ) {
        // Expansion allows for negative VP. Remove this check for now.
        // if ($this->victoryPoints < 1 || $this->victoryPoints > 3) {
        //    throw new \InvalidArgumentException('Planet victory points must be between 1 and 3.');
        // }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            victoryPoints: (int) $data['victory_points'],
            name: $data['name'] ?? null,
            class: isset($data['class']) && $data['class'] !== null
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
            'class'          => $this->class?->value,
            'abilities'      => array_map(
                fn (PlanetAbility $ability) => $ability->toArray(),
                $this->abilities
            ),
        ];
    }

    /**
     * A simple default deck you can replace later.
     *
     * @return Planet[]
     */
    public static function defaultDeck(): array
    {
        $planets = [];
        $id = 1;

        $vpMap = [
            PlanetClass::TradePostColony->value   => [1, 1, 2, 2, 3],
            PlanetClass::ResearchColony->value    => [1, 1, 2, 2, 3],
            PlanetClass::MiningColony->value      => [1, 1, 2, 2, 3],
        ];

        foreach ($vpMap as $classValue => $vps) {
            // Turn the string back into a PlanetClass enum
            $class = PlanetClass::from((string) $classValue);

            foreach ($vps as $vp) {
                $planets[] = new self(
                    id: 'P'.$id,
                    victoryPoints: $vp,
                    name: "Planet {$id}",
                    class: $class,
                    abilities: [],
                );
                $id++;
            }
        }

        return $planets;
    }
}
