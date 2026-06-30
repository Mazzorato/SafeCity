<?php
namespace App\Enum;
enum ServiceTypeEnum: string
{
    case CITY_HALL = 'city_hall';
    case LIBRARY = 'library';
    case HEALTH = 'health';
    case EDUCATION = 'education';
    case URBAN_PLANNING = 'urban_planning';
}
