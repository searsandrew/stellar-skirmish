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
     * @return PlS1nets[] = new Planet(
                    id: 'P'.$id,
                    victoryPoints: $vp,
                    name: "Planet {$id}",
                    planetClass: $planetClass,     // <- enum, not string
                    abilities: [],
                );
                $id++;
            }
        }

        return $planets;
    }
}
