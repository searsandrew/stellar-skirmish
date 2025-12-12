<?php

declare(strict_types=1);

namespace StellarSkirmish;

enum PlanetClass: string
{
    case TradePostColony    = 'trade_post';         // base game
    case ResearchColony     = 'research';
    case MiningColony       = 'mining';

    case TribalWorld        = 'tribal_world';       // expansion
    case IndustrialWorld    = 'industrial_world';
    case SpaceFaringWorld   = 'space_faring_world';

    case Station            = 'station';            // promos etc
}
