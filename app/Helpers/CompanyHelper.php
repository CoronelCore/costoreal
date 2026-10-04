<?php

namespace App\Helpers;

use App\Models\Company;
use App\Modules\Access\AccessContext;

class CompanyHelper
{
    public static function getActiveCompany(): ?Company
    {
        return AccessContext::forRequest(request())->activeCompany();
    }

    public static function getActiveCompanyId(): ?int
    {
        return self::getActiveCompany()?->id;
    }

    public static function hasActiveCompany(): bool
    {
        return self::getActiveCompany() !== null;
    }

    public static function getUserCompanies()
    {
        return AccessContext::forRequest(request())->companies();
    }

    public static function getActiveCompanyName(): string
    {
        return self::getActiveCompany()?->nombre ?? 'Todas las empresas';
    }

    public static function getActiveCompanyIcon(): string
    {
        return self::getActiveCompany()?->icono ?? '🏢';
    }
}
