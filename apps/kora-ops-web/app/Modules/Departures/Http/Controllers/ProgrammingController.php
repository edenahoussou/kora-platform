<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProgrammingSelection;
use App\Models\User;
use App\Modules\Departures\Http\Requests\PlanningMutationRequest;
use App\Modules\Departures\Http\Requests\PlanningQueryRequest;
use App\Modules\Departures\Http\Requests\ProgrammeRequest;
use App\Modules\Departures\Http\Requests\TemplateRequest;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\DepartureTemplate;
use App\Modules\Departures\Models\DepartureTemplateVersion;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\Departures\Models\ProgrammingRun;
use App\Modules\Departures\Services\PlanningReadService;
use App\Modules\Departures\Services\PlanningService;
use App\Modules\Departures\Services\TemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammingController extends Controller
{
    public function __construct(private PlanningReadService $read, private PlanningService $planning, private TemplateService $templates) {}

    public function index(PlanningQueryRequest $request): Response
    {
        return Inertia::render('programming/index', $this->read->workspace($request->validated()) + ['canCancel' => $request->user()->can('departures.cancel')]);
    }

    public function createTemplate(PlanningQueryRequest $request): Response
    {
        $initial = null;
        if ($request->filled('source')) {
            $source = DepartureTemplate::query()->with('versions')->where('uuid', $request->input('source'))->firstOrFail();
            $initial = ['name' => $source->name.' — copie', 'slots' => $source->draft['slots'] ?? $source->versions->sortByDesc('number')->first()->slots ?? []];
        } elseif ($request->filled('departure')) {
            $departure = Departure::query()->where('uuid', $request->input('departure'))->firstOrFail();
            $initial = ['name' => $departure->line->name.' — semaine type', 'slots' => [$this->read->departureSlot($departure)]];
        }

        return Inertia::render('programming/template-editor', ['template' => null, 'initial' => $initial, 'references' => $this->read->references()]);
    }

    public function editTemplate(PlanningQueryRequest $request, DepartureTemplate $template): Response
    {
        return Inertia::render('programming/template-editor', ['template' => $this->read->template($template), 'references' => $this->read->references()]);
    }

    public function saveTemplate(TemplateRequest $request, ?DepartureTemplate $template = null): RedirectResponse
    {
        $template = $this->templates->save($request->validated(), $this->actor($request->user()), $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('ready') ? 'Version du modèle disponible.' : 'Brouillon enregistré.']);

        return to_route('programming.templates.edit', ['template' => $template->uuid]);
    }

    public function archiveTemplate(PlanningMutationRequest $request, DepartureTemplate $template): RedirectResponse
    {
        abort_unless($request->input('operation') === 'archive', 422);
        DB::transaction(function () use ($request, $template): void {
            $template = DepartureTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            abort_unless($template->revision === $request->integer('revision'), 409, 'Le modèle a changé. Rechargez-le.');
            $template->update(['status' => 'archived', 'revision' => $template->revision + 1]);
            activity()->causedBy($request->user())->performedOn($template)->log('programming.template.archived');
        });

        return to_route('programming.index', ['tab' => 'templates']);
    }

    public function createProgramme(PlanningQueryRequest $request): Response
    {
        return Inertia::render('programming/programme-editor', ['selectedTemplate' => $request->input('template'), 'templates' => DepartureTemplate::query()->with('versions')->where('status', 'available')->get()->map(fn ($t): array => $this->read->template($t))->all(), 'references' => $this->read->references()]);
    }

    public function previewProgramme(ProgrammeRequest $request): JsonResponse
    {
        $programme = $this->planning->programme($request->validated(), $this->actor($request->user()), false);

        return response()->json($this->planning->preview($programme, $programme->starts_on, $programme->starts_on->addDays(89)->min($programme->ends_on)));
    }

    public function storeProgramme(ProgrammeRequest $request): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $programme = DB::transaction(function () use ($request, $actor): DepartureProgramme {
            $programme = $this->planning->programme($request->validated(), $actor);
            $this->planning->generate($programme, $programme->starts_on, $programme->starts_on->addDays(89)->min($programme->ends_on), $actor);

            return $programme;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme ajouté au planning. Les départs ne sont pas encore publiés.']);

        return to_route('programming.index', ['programme' => $programme->uuid, 'date' => $programme->starts_on->toDateString()]);
    }

    public function item(PlanningQueryRequest $request, PlannedDeparture $item): JsonResponse
    {
        return response()->json($this->read->item($item, true));
    }

    public function mutate(PlanningMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        abort_unless(in_array($data['operation'], ['assign', 'exclude', 'change', 'publish'], true), 422);

        $actor = $this->actor($request->user());
        if (count($data['items']) > 25) {
            DB::transaction(function () use ($data, $actor): void {
                $occurrences = PlannedDeparture::query()->whereIn('uuid', array_column($data['items'], 'uuid'))->get()->keyBy('uuid');
                $groups = [];
                foreach ($data['items'] as $input) {
                    $groups[$occurrences[$input['uuid']]->programme_id][] = $input;
                }
                foreach ($groups as $programmeId => $selection) {
                    $run = ProgrammingRun::query()->create([
                        'programme_id' => $programmeId, 'operation' => $data['operation'], 'status' => 'queued', 'processed' => 0,
                        'created_by' => $actor->id, 'result' => ['selection' => $selection, 'changes' => $data['changes'] ?? [], 'offset' => 0, 'total' => count($selection), 'errors' => []],
                    ]);
                    ProcessProgrammingSelection::dispatch($run->id)->afterCommit();
                }
            });

            return response()->json(['processed' => 0, 'queued' => count($data['items']), 'errors' => []], 202);
        }

        return response()->json($this->planning->mutate($data['items'], $data['operation'], $data['changes'] ?? [], $actor));
    }

    public function programmeAction(PlanningMutationRequest $request, DepartureProgramme $programme): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actor($request->user());
        $from = CarbonImmutable::parse($data['from'] ?? now()->toDateString());
        $until = CarbonImmutable::parse($data['until'] ?? $from->addDays($programme->horizon_days - 1)->toDateString());
        if ($data['operation'] === 'preview') {
            return response()->json($this->planning->preview($programme, $from, $until));
        }
        if ($data['operation'] === 'generate') {
            abort_unless(! empty($data['token']), 422, 'Prévisualisez la période avant génération.');

            return response()->json($this->read->run($this->planning->generate($programme, $from, $until, $actor, $data['token'])));
        }
        if ($data['operation'] === 'revision') {
            $version = DepartureTemplateVersion::query()->where('uuid', $data['version_uuid'])->firstOrFail();

            return response()->json($this->planning->revise($programme, $version, $from, (bool) ($data['apply'] ?? false), $data['token'] ?? null, $actor));
        }
        abort_unless($data['operation'] === 'automatic', 422);
        DB::transaction(function () use ($programme, $data, $actor): void {
            $programme = DepartureProgramme::query()->lockForUpdate()->findOrFail($programme->id);
            abort_unless($programme->revision === ($data['revision'] ?? null), 409, 'Le programme a changé. Rechargez-le.');
            if ($data['automatic']) {
                $horizon = $data['horizon_days'] ?? $programme->horizon_days;
                $from = now()->toImmutable()->startOfDay()->max($programme->starts_on);
                $until = now()->toImmutable()->startOfDay()->addDays($horizon - 1)->min($programme->ends_on);
                abort_if($from->gt($until), 422, 'La période ne recoupe pas l’horizon automatique.');
                abort_unless(isset($data['token']) && hash_equals($this->planning->preview($programme, $from, $until)['token'], $data['token']), 409, 'Vérifiez l’horizon automatique avant activation.');
            }
            $programme->update(['automatic_approved_by' => $data['automatic'] ? $actor->id : null, 'automatic' => $data['automatic'], 'horizon_days' => $data['horizon_days'] ?? $programme->horizon_days, 'revision' => $programme->revision + 1]);
            activity()->causedBy($actor)->performedOn($programme)->log($data['automatic'] ? 'programming.automatic.enabled' : 'programming.automatic.paused');
        });

        return response()->json(['saved' => true]);
    }

    private function actor(mixed $user): User
    {
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
