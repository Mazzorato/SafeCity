<?php

namespace App\Enum;

enum TypeServiceEnum : string 
{
    case MAIRIE = 'mairie';
    case BIBLIOTHEQUE = 'bibliothèque';
    case SANTE = 'santé';
    case EDUCATION = 'éducation';
    case URBANISME = 'urbanisme';
}