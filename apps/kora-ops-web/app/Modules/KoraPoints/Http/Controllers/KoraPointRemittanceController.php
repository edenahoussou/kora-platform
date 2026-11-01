<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KoraPoints\Http\Requests\CreateKoraPointRemittanceRequest;
use App\Modules\KoraPoints\Http\Requests\ReceiveKoraPointRemittanceRequest;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointRemittanceLine;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointRemittanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointRemittanceController extends Controller
{
    public function __construct(private readonly KoraPointRemittanceService $service, private readonly KoraPointAccess $access) {}

    /**
     * Renders the remittance slips visible to the actor.
     */
    public function index(Request $request): Response
    {
        $this->access->authorize($request->user(), 'kora-points.view');
        $saleIds = $this->access->sales($request->user())->select('id');
        $query = KoraPointRemittance::query()->whereHas('lines', fn ($query) => $query->whereIn('sale_id', $saleIds));

        return Inertia::render('kora-points/remittances/index', ['remittances' => $query->latest('id')->paginate(30)->through(fn (KoraPointRemittance $remittance): array => $this->present($remittance))]);
    }

    /**
     * Renders a single remittance detail with its covered sales.
     */
    public function show(Request $request, KoraPointRemittance $remittance): Response
    {
        $this->service->requireView($request->user(), $remittance);

        return Inertia::render('kora-points/remittances/show', ['remittance' => $this->present($remittance), 'lines' => $remittance->lines()->with('sale.point')->get()->map(fn (KoraPointRemittanceLine $line): array => ['sale_uuid' => $line->sale->uuid, 'point_uuid' => $line->sale->point->uuid, 'amount' => $line->amount])->all()]);
    }

    /**
     * Creates a draft remittance from the selected sales.
     */
    public function store(CreateKoraPointRemittanceRequest $request): RedirectResponse
    {
        $remittance = $this->service->create($request->user(), $request->validated('sale_uuids'), $request->validated('method'), $request->validated('reference'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bordereau préparé.']);

        return to_route('kora-points.remittances.show', $remittance->uuid);
    }

    /**
     * Declares the remittance to KORA finance for control.
     */
    public function submit(Request $request, KoraPointRemittance $remittance): RedirectResponse
    {
        $this->service->submit($request->user(), $remittance);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reversement déclaré ; réception KORA en attente.']);

        return back();
    }

    /**
     * Records the finance reception decision (validate, reconcile or reject).
     */
    public function receive(ReceiveKoraPointRemittanceRequest $request, KoraPointRemittance $remittance): RedirectResponse
    {
        $this->service->receive($request->user(), $remittance, (int) $request->validated('received_amount'), $request->boolean('reject'), $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contrôle du reversement enregistré.']);

        return back();
    }

    /** @return array<string, mixed> */
    /**
     * Maps a remittance to its public representation without internal identifiers.
     *
     * @return array<string, mixed>
     */
    private function present(KoraPointRemittance $remittance): array
    {
        return ['uuid' => $remittance->uuid, 'status' => $remittance->status, 'amount' => $remittance->amount,
            'received_amount' => $remittance->received_amount, 'method' => $remittance->method, 'reference' => $remittance->reference,
            'note' => $remittance->note, 'submitted_at' => $remittance->submitted_at?->toIso8601String(), 'validated_at' => $remittance->validated_at?->toIso8601String()];
    }
}
