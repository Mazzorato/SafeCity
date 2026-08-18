<?php
namespace App\Enum;
/**
 * Définit les valeurs métier autorisées pour TransportTypeEnum.
 */
enum TransportTypeEnum: string
{
    case METRO = 'metro';
    case BUS = 'bus';
    case TRAM = 'tram';
}


