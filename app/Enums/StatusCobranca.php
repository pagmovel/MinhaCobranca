<?php

namespace App\Enums;

enum StatusCobranca: string
{
    case Pendente = 'pendente';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
}
