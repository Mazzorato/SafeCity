<?php 

namespace App\Enum;

/**
 * Définit les valeurs métier autorisées pour NewsCategoryEnum.
 */
enum NewsCategoryEnum : string
{
    case SECURITE = "securite";
    case SANTE = "sante";
    case MOBILITE = "mobilite";
    case TRAVAUX = "travaux";
}


