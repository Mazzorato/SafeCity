<?php
namespace App\Enum;
/**
 * Définit les valeurs métier autorisées pour GravityLevelEnum.
 */
enum GravityLevelEnum: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
