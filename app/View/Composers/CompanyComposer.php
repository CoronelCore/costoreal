<?php

namespace App\View\Composers;

use App\Helpers\CompanyHelper;
use Illuminate\View\View;

class CompanyComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $view->with([
            'userCompanies' => CompanyHelper::getUserCompanies(),
            'activeCompany' => CompanyHelper::getActiveCompany(),
            'activeCompanyId' => CompanyHelper::getActiveCompanyId(),
            'activeCompanyName' => CompanyHelper::getActiveCompanyName(),
        ]);
    }
}
