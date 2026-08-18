<?php
namespace App\Enum;
/**
 * Définit les valeurs métier autorisées pour ServiceTypeEnum.
 */
enum ServiceTypeEnum: string
{
    case CITY_HALL = 'city_hall';
    case LIBRARY = 'library';
    case HEALTH = 'health';
    case EDUCATION = 'education';
    case URBAN_PLANNING = 'urban_planning';
}


