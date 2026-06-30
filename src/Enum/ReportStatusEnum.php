<?php
namespace App\Enum;
enum ReportStatusEnum: string
{
    case REPORTED = 'reported';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
}
