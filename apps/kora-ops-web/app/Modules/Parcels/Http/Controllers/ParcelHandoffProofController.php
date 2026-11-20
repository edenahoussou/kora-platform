<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelDocuments;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParcelHandoffProofController extends Controller
{
    public function __invoke(Request $request, Parcel $parcel, ParcelAccess $access, ParcelDocuments $documents): Response
    {
        $access->operations($request->user())->findOrFail($parcel->id);

        return Inertia::render('parcel-handoff-proof', [
            'proof' => $documents->handoffProof($parcel, $request->user()),
        ]);
    }
}
