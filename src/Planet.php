<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class Planet
{
    public function __construct(
        public readonly string $id,
        public readonly int $victoryPoints,
        public readonly ?string $name = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $abilities = null,
    ) {
        if ($this->victoryPoints < 1 || $this->victoryPoints > 3) {
            throw new \InvalidArgumentException('Planet victory points must be between 1 and 3.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            victoryPoints: (int) $data['victory_points'],
            name: $data['name'] ?? null,
            abilities: $data['abilities'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'victory_points' => $this->victoryPoints,
            'name'           => $this->name,
            'abilities'      => $this->abilities,
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
            1 => 5,
            2 => 5,
            3 => 5,
        ];

        foreach ($vpMap as $vp => $count) {
            for ($i = 0; $i < $count; $i++, $id++) {
                $planets[] = new self(
                    id: 'P'.$id,
                    victoryPoints: $vp,
                    name: "Planet {$id}"
                );
            }
        }

        return $planets;
    }
}
