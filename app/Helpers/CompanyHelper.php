<?php

namespace App\Helpers;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class CompanyHelper
{
    /**
     * Obtener la empresa activa de la sesión
     */
    public static function getActiveCompany(): ?Company
    {
        $companyId = session('active_company_id');

        if ($companyId) {
            return Company::find($companyId);
        }

        return null;
    }

    /**
     * Obtener ID de la empresa activa
     */
    public static function getActiveCompanyId(): ?int
    {
        return session('active_company_id');
    }

    /**
     * Verificar si hay una empresa seleccionada
     */
    public static function hasActiveCompany(): bool
    {
        return session()->has('active_company_id');
    }

    /**
     * Obtener todas las empresas del usuario autenticado
     */
    public static function getUserCompanies()
    {
        if (!Auth::check()) {
            return collect();
        }

        return Auth::user()->companies()->where('activa', true)->get();
    }

    /**
     * Obtener nombre de la empresa activa o "Todas las empresas"
     */
    public static function getActiveCompanyName(): string
    {
        $company = self::getActiveCompany();

        return $company ? $company->nombre : 'Todas las empresas';
    }

    /**
     * Obtener ícono de la empresa activa
     */
    public static function getActiveCompanyIcon(): string
    {
        $company = self::getActiveCompany();

        return $company && $company->icono ? $company->icono : '🏢';
    }
}
