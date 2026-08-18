<?php
namespace App\Enum;
/**
 * Définit les valeurs métier autorisées pour ReportStatusEnum.
 */
enum ReportStatusEnum: string
{
    case REPORTED = 'reported';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
}
