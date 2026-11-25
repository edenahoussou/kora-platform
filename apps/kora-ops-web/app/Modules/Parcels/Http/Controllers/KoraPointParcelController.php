<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointParcelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof User
                && $actor->hasRole('kora_point_agent')
                && $actor->agency_id !== null,
            403,
        );

        return Inertia::render('parcels');
    }
}
