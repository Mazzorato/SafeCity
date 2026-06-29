<?php

namespace App\Enum;

enum StatutSignalementEnum: string
{
    case SIGNALE = 'signalé';
    case PRIS_EN_COMPTE = 'pris en compte';
    case RESOLU = 'résolu';
}