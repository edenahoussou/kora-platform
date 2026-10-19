<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CashMediaController extends Controller
{
    public function show(Request $request, string $mediaUuid): mixed
    {
        $media = Media::query()
            ->where('uuid', $mediaUuid)
            ->whereIn('model_type', [CashLedger::class, CashMovement::class])
            ->firstOrFail();
        $model = $media->model;
        $session = $model instanceof CashLedger
            ? $model->cashSession
            : ($model instanceof CashMovement ? $model->cashSession : null);

        abort_unless(
            $session !== null
                && $request->user()->agency_id === $session->agency_id
                && $request->user()->can('cash.media.view'),
            403,
        );

        $conversion = $request->query('conversion');
        if (in_array($conversion, ['thumb', 'preview'], true)) {
            $path = $media->getPath($conversion);
            abort_unless(is_file($path), 404);

            return response()->file($path);
        }

        return $media->toInlineResponse($request);
    }
}
