<?php

declare(strict_types=1);

namespace StellarSkirmish;

enum PlanetClass: string
{
    case TradePostColony    = 'trade_post_colony';         // base game
    case ResearchColony     = 'research_colony';
    case MiningColony       = 'mining_colony';

    case TribalWorld        = 'tribal_world';       // expansion
    case IndustrialWorld    = 'industrial_world';
    case SpaceFaringWorld   = 'space_faring_world';

    case Station            = 'station';            // promos etc
}
