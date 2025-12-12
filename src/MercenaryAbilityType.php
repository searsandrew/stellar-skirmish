<?php

namespace StellarSkirmish;

enum MercenaryAbilityType : string
{
    case OverpowerFifteen    = 'overpower_fifteen';
    case RevealOpponentsCorp = 'reveal_opponents_corp';
    case WinAllTies          = 'win_all_ties';
    case ReturnOnce          = 'return_once';
    case DiscardPlanetAndDrawNew = 'discard_planet_draw_new';
    case PeekNextPlanet      = 'peek_next_planet';
}