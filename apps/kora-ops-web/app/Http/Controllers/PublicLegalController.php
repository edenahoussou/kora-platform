<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PublicLegalController extends Controller
{
    public function privacyPolicy(): Response
    {
        return Inertia::render('public/privacy-policy', $this->pageProps());
    }

    public function accountDeletion(): Response
    {
        return Inertia::render('public/account-deletion', $this->pageProps());
    }

    /**
     * @return array{supportEmail: string, lastUpdated: string}
     */
    private function pageProps(): array
    {
        return [
            'supportEmail' => (string) config('legal.support_email'),
            'lastUpdated' => (string) config('legal.last_updated_at'),
        ];
    }
}
