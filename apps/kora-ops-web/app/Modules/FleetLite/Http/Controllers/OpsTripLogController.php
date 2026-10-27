<?php

namespace App\Modules\FleetLite\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\FleetLite\Actions\RecordOdometerReadingAction;
use App\Modules\FleetLite\Actions\SubmitTripExpenseAction;
use App\Modules\FleetLite\Exceptions\TripLogException;
use App\Modules\FleetLite\Http\Requests\RecordOdometerRequest;
use App\Modules\FleetLite\Http\Requests\StoreTripExpenseRequest;
use App\Modules\FleetLite\Services\TripLogReadService;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS Mobile — Carnet de bord
 *
 * Saisies terrain du chauffeur et du convoyeur sur un départ qui leur est
 * affecté (permission `trip-logs.create`) : kilométrage départ/arrivée,
 * carburant (litres, prix, station, photo du ticket), péages et taxes
 * frontières. Les dépenses partent « en attente » de validation au bureau
 * (module Dépenses) avec un e-mail détaillé aux opérations, administrateurs
 * en copie.
 *
 * Chauffeur : sans terminal P501 (sauf OPS_FORCE_TERMINAL_FOR_ALL=true) ;
 * convoyeur : en-tête X-Terminal-Serial et session OPS comme les autres
 * routes terrain.
 */
class OpsTripLogController extends Controller
{
    public function __construct(
        private CrewDepartureScope $scope,
        private TripLogReadService $reader,
    ) {}

    /**
     * Carnet de bord du départ : relevés compteur et dépenses de voyage
     * saisies par l'équipage.
     *
     * @authenticated
     *
     * @response 200 scenario="Carnet de bord" {"data":{"departure_uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","bus":{"uuid":"…","registration_number":"AB 1234 RB"},"line_code":"COT-LFW","last_known_odometer_km":182300,"odometer":{"start":{"uuid":"…","value_km":182450,"recorded_at":"2026-10-27T07:55:00.000Z","recorded_by":"Koffi A."},"end":null,"distance_km":null},"expenses":[]}}
     * @response 403 scenario="Départ non affecté" {"error":{"code":"departure_not_assigned","message":"Ce départ ne vous est pas affecté."}}
     */
    public function show(Request $request, Departure $departure): JsonResponse
    {
        $this->authorizeDeparture($request, $departure);

        return response()->json(['data' => $this->reader->forDeparture($departure)]);
    }

    /**
     * Enregistre ou corrige le kilométrage de départ (`start`) ou d'arrivée
     * (`end`). L'arrivée ne peut pas être inférieure au départ.
     *
     * @authenticated
     *
     * @response 422 scenario="Arrivée < départ" {"error":{"code":"odometer_inconsistent","message":"Le kilométrage d’arrivée (182 400 km) ne peut pas être inférieur à celui du départ (182 450 km)."}}
     */
    public function odometer(RecordOdometerRequest $request, Departure $departure, RecordOdometerReadingAction $record): JsonResponse
    {
        $user = $this->authorizeDeparture($request, $departure);

        [, $created] = $record->execute(
            $user,
            $departure,
            $this->terminal($request),
            $request->kind(),
            $request->valueKm(),
            $request->recordedAt(),
            $request->clientUuid(),
        );

        return response()->json(['data' => $this->reader->forDeparture($departure)], $created ? 201 : 200);
    }

    /**
     * Déclare une dépense de voyage : carburant, péage ou taxe frontière
     * (multipart si photo). Idempotent par `client_uuid`. Statut initial :
     * `pending` (validation au bureau).
     *
     * @authenticated
     *
     * @response 201 scenario="Plein enregistré" {"data":{"uuid":"…","reference":"DEP-20261027-AB12","client_uuid":"…","kind":"fuel","kind_label":"Carburant","amount":44540,"currency":"XOF","liters":65.5,"unit_price":680,"station_label":"Total Godomey","checkpoint_label":null,"country_code":null,"odometer_km":182510,"payment_channel":"cash","occurred_at":"2026-10-27T08:30:00.000Z","status":"pending","status_label":"En attente","rejection_reason":null,"has_photo":true,"submitted_by":"Koffi A."}}
     * @response 409 scenario="Identifiant déjà utilisé" {"error":{"code":"trip_entry_uuid_conflict","message":"Cet identifiant de saisie est déjà utilisé."}}
     */
    public function storeExpense(StoreTripExpenseRequest $request, Departure $departure, SubmitTripExpenseAction $submit): JsonResponse
    {
        $user = $this->authorizeDeparture($request, $departure);

        [$expense, $created] = $submit->execute($user, $departure, $this->terminal($request), $request->tripExpenseData());

        $expense->load(['tripDetails.submitter', 'media']);

        return response()->json(['data' => $this->reader->expense($expense)], $created ? 201 : 200);
    }

    /**
     * @throws TripLogException
     */
    private function authorizeDeparture(Request $request, Departure $departure): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->scope->canRecordTripLog($user, $departure)) {
            throw TripLogException::notAssigned();
        }

        return $user;
    }

    private function terminal(Request $request): ?TerminalP501
    {
        $terminal = $request->attributes->get('terminal');

        return $terminal instanceof TerminalP501 ? $terminal : null;
    }
}
