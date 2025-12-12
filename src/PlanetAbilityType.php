<?php

declare(strict_types=1);

namespace StellarSkirmish;

enum PlanetAbilityType: string
{
    /**
     * "Double the value of the next planet card, do not perform combat for this card."
     * Triggered when this planet is claimed.
     */
    case DoubleNextPlanetNoCombat = 'double_next_planet_no_combat';

    /**
     * "Earn extra VP based on how many copies of a given class you have."
     * e.g. mining set bonuses.
     */
    case ClassSetBonus = 'class_set_bonus';

    /**
     * "You select what class the world will be."
     */
    case ChooseClass = 'choose_class';

    /**
     * "You lose X VP" (liability worlds)
     */
    case LoseVictoryPoints = 'lose_victory_points';
}
