<?php

declare(strict_types=1);

namespace StellarSkirmish;

/**
 * Lightweight ability descriptor.
 *
 * For simplicity, parameters are an array; specific abilities
 * will interpret them in scoring / resolution code.
 */
final class PlanetAbility
{
    public function __construct(
        public readonly PlanetAbilityType $type,
        /** @var array<string, mixed> */
        public readonly array $params = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: PlanetAbilityType::from($data['type']),
            params: $data['params'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'type'   => $this->type->value,
            'params' => $this->params,
        ];
    }
}
