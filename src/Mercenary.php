<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class Mercenary
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $baseStrength,
        public readonly MercenaryAbilityType $abilityType,
        /** @var array<string, mixed> */
        public readonly array $params = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            baseStrength: (int) $data['base_strength'],
            abilityType: MercenaryAbilityType::from($data['ability_type']),
            params: $data['params'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'base_strength' => $this->baseStrength,
            'ability_type' => $this->abilityType->value,
            'params' => $this->params,
        ];
    }
}