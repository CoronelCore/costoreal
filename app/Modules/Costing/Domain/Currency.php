<?php

namespace App\Modules\Costing\Domain;

use DomainException;

enum Currency: string
{
    case CLP = 'CLP';
    case USD = 'USD';
    case EUR = 'EUR';
    case BRL = 'BRL';
    case PEN = 'PEN';
    case CNY = 'CNY';

    public static function supported(string $code): self
    {
        return self::tryFrom($code) ?? throw new DomainException("La moneda {$code} no está habilitada para costeo.");
    }
}
