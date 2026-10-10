<?php

namespace App\Enums;

enum StatusUsuario: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Bloqueado = 'bloqueado';
}
