<?php

namespace App\Modules\Passengers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Passengers\Actions\RequestAccountErasureAction;
use App\Modules\Passengers\Http\Requests\DeleteAccountRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountErasureApiController extends Controller
{
    public function __construct(
        private readonly RequestAccountErasureAction $requestErasure,
    ) {}

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $this->requestErasure->execute(
            $request->user(),
            (string) $request->validated('pin'),
            $request->validated('reason'),
            $request->ip(),
        );

        return response()->json(
            ['data' => ['status' => 'requested']],
            Response::HTTP_ACCEPTED,
        );
    }
}
