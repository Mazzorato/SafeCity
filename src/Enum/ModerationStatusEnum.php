<?php

namespace App\Enum;

/**
 * Définit les valeurs métier autorisées pour ModerationStatusEnum.
 */
enum ModerationStatusEnum: string
{
    case FLAGGED = 'flagged';
    case HIDDEN = 'hidden';
    case DISMISSED = 'dismissed';
}
