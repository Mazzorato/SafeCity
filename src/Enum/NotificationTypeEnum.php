<?php
namespace App\Enum;
enum NotificationTypeEnum: string
{
    case EMERGENCY = 'emergency';
    case WEATHER = 'weather';
    case TRANSPORT = 'transport';
    case EVENT = 'event';
}
