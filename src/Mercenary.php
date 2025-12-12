<?php

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
}