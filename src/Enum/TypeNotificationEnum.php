<?php

namespace App\Enum;

enum TypeNotificationEnum: string
{
    case URGENCE = 'urgence';
    case METEO = 'météo';
    case TRANSPORT = 'transport';
    case EVENEMENT = 'evenement';
}