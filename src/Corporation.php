<?php

declare(strict_types=1);

namespace StellarSkirmish;

final class Corporation
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        /**
         * Multipliers per class.
         * e.g. [PlanetClass::Mining->value => 2.0, ...]
         *
         * Values can be:
         *  -  2.0 (double)
         *  -  1.0 (no change)
         *  - -1.0 (X*-1, negative)
         *  -  1.5 (expansion corps)
         */
        public readonly array $classMultipliers,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            classMultipliers: $data['class_multipliers'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'class_multipliers' => $this->classMultipliers,
        ];
    }

    public function multiplierForClass(?PlanetClass $class): float
    {
        if ($class === null) {
            return 1.0;
        }

        return (float) ($this->classMultipliers[$class->value] ?? 1.0);
    }
}
