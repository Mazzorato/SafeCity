<?php
namespace App\Enum;
/**
 * Définit les valeurs métier autorisées pour NotificationTypeEnum.
 */
enum NotificationTypeEnum: string
{
    case EMERGENCY = 'emergency';
    case TRANSPORT = 'transport';
    case EVENT = 'event';
    case MODERATION = 'moderation';
}
