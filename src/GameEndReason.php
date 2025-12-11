<?php

declare(strict_types=1);

namespace StellarSkirmish;

enum GameEndReason: string
{
  /**
   * Default game end; all ships used, no special anomaly.
   */
  case Normal = 'normal';

  /**
   * Players tied when there were no planets left to add to the pot.
   * The pot was discarded and the game ended with those planets unclaimed.
   */
  case FinalTiePotDiscarded = 'final_tie_pot_discarded';

  /**
   * All ships were used but some planets remained unclaimed (either still
   * in the deck or effectively unreachable).
   */
  case ShipsExhaustedPlanetsRemaining = 'ships_exhausted_planets_remaining';

  /**
   * Defensive condition: at least one player ran out of cards before the others did.
   * This "should be impossible" under normal rules, but is handled explicitly.
   */
  case PlayerOutOfCardsEarly = 'player_out_of_cards_early';
}
