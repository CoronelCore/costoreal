<?php

namespace App\Http\Middleware;

use App\Helpers\CompanyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function onVersionChange(Request $request, Response $response): Response
    {
        // The rendered v3 response already consumed flash before a 409 reload.
        // Preserve it for the subsequent full visit to the updated build.
        if ($response instanceof JsonResponse) {
            $flash = $response->getData(true)['flash'] ?? [];
            if (is_array($flash) && $flash !== []) {
                Inertia::flash($flash);
            }
        }

        return parent::onVersionChange($request, $response);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Bridge existing redirect messages to v3 flash, outside browser history.
        foreach (['success', 'error', 'info'] as $key) {
            $message = $request->session()->pull($key);
            if (is_string($message) && $message !== '') {
                Inertia::flash($key, $message);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                'activeCompany' => CompanyHelper::getActiveCompany(),
            ],
            'company' => [
                'active' => CompanyHelper::getActiveCompany(),
                'activeId' => CompanyHelper::getActiveCompanyId(),
                'activeName' => CompanyHelper::getActiveCompanyName(),
                'userCompanies' => CompanyHelper::getUserCompanies(),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
