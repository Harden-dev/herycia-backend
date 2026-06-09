<?php

namespace App\Enums;

enum SalonCity: string
{
    case Abidjan = 'Abidjan';
    case Bouake = 'Bouaké';
    case Yamoussoukro = 'Yamoussoukro';
    case Daloa = 'Daloa';
    case SanPedro = 'San-Pédro';
    case Korhogo = 'Korhogo';
    case Man = 'Man';
    case Gagnoa = 'Gagnoa';
    case Abengourou = 'Abengourou';
    case Divo = 'Divo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
