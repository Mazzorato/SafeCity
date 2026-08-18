<?php

namespace App\Enum;

/**
 * Définit les valeurs métier autorisées pour ModerationTargetEnum.
 */
enum ModerationTargetEnum: string
{
    case COMMENT = 'comment';
    case PHOTO = 'photo';
}
