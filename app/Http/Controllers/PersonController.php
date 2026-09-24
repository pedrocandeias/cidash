<?php

namespace App\Http\Controllers;

use App\Core\ExpertiseAreas;
use App\Core\RecordPage;
use App\Http\Requests\PersonRequest;
use App\Models\ExpertiseArea;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonController extends Controller
{
    public function __construct(private WorkspaceContext $context, private ExpertiseAreas $areas) {}

    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q'));
        $area = $request->integer('area') ?: null;

        // The deceased are only in Dead or Alive: they are no longer suggested to the media.
        $people = Person::query()
            ->with('areas:id,name')
            ->whereNull('deceased_on')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($builder) => collect(['name', 'affiliation', 'academic_title', 'short_bio', 'bio', 'keywords'])
                ->each(fn (string $column) => $builder->orWhere($column, 'like', '%'.$query.'%'))))
            ->when($area !== null, fn ($builder) => $builder->whereHas('areas', fn ($builder) => $builder->whereKey($area)))
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person) => $this->summary($person));

        return Inertia::render('people/index', [
            'people' => $people,
            'filters' => ['q' => $query, 'area' => $area],
            'areas' => ExpertiseArea::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        $person = DB::transaction(function () use ($request) {
            $person = Person::create([
                ...$request->safe()->except(['areas', 'photo', 'remove_photo', 'cv', 'remove_cv']),
                'last_reviewed_at' => $request->input('last_reviewed_at') ?: now(),
            ]);
            $this->areas->sync($person, $request->input('areas', []));
            $this->storePhoto($request, $person);
            $this->storeCv($request, $person);

            return $person;
        });

        return to_route('people.show', $person);
    }

    public function show(Request $request, Person $person, RecordPage $page): Response
    {
        return Inertia::render('people/show', [
            'person' => [
                ...$this->summary($person->load('areas:id,name')),
                'bio' => $person->bio,
                'keywords' => $person->keywords,
                'languages' => $person->languages,
                'media_notes' => $person->media_notes,
                'consent_at' => $person->consent_at?->toDateString(),
                'last_reviewed_at' => $person->last_reviewed_at?->toDateString(),
                'career' => $person->career,
                'cv' => $person->cv_path !== null ? ['name' => $person->cv_name, 'url' => route('people.cv', $person, absolute: false)] : null,
                'photos' => $person->photos()->get()->map(fn (PersonPhoto $photo) => [
                    'id' => $photo->id,
                    'url' => route('people.photos.show', $photo, absolute: false),
                ]),
                'obituary' => $person->obituary,
                'obituary_updated_at' => $person->obituary_updated_at?->toIso8601String(),
            ],
            'members' => $this->members(),
            ...$page->for($person->record, $request->user()),
            'can' => ['delete' => $request->user()->can('delete', $person)],
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        DB::transaction(function () use ($request, $person) {
            $person->update($request->safe()->except(['areas', 'photo', 'remove_photo', 'cv', 'remove_cv']));

            if ($request->has('areas')) {
                $this->areas->sync($person, $request->input('areas', []));
            }

            if ($request->boolean('remove_photo')) {
                $this->deletePhoto($person);
            }

            $this->storePhoto($request, $person);

            if ($request->boolean('remove_cv')) {
                $this->deleteCv($person);
            }

            $this->storeCv($request, $person);
        });

        return back();
    }

    public function destroy(Person $person): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $this->deletePhoto($person);
        $this->deleteCv($person);
        $person->photos()->get()->each->delete();
        $person->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile deleted.')]);

        return to_route('people.index');
    }

    /**
     * Photos are private files, served only to the team.
     */
    public function photo(Person $person): StreamedResponse
    {
        abort_if($person->photo_path === null || ! Storage::disk('local')->exists($person->photo_path), 404);

        return Storage::disk('local')->response($person->photo_path);
    }

    /**
     * Dead or Alive: obituaries prepared in advance, and the people who have died.
     */
    public function obituaries(Request $request): Response
    {
        $filter = in_array($request->query('filter'), ['alive', 'deceased'], true) ? $request->query('filter') : 'all';

        $people = Person::query()
            ->with('areas:id,name')
            ->where(fn ($query) => $query->whereNotNull('obituary')->orWhereNotNull('deceased_on'))
            ->when($filter === 'alive', fn ($query) => $query->whereNull('deceased_on'))
            ->when($filter === 'deceased', fn ($query) => $query->whereNotNull('deceased_on'))
            ->orderByRaw('deceased_on is null')
            ->orderByDesc('deceased_on')
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person) => [
                ...$this->summary($person),
                'has_obituary' => $person->obituary !== null,
                'obituary_updated_at' => $person->obituary_updated_at?->toIso8601String(),
            ]);

        return Inertia::render('people/obituaries', [
            'people' => $people,
            'filter' => $filter,
            // Living people without an obituary yet, to start one.
            'candidates' => Person::whereNull('obituary')->whereNull('deceased_on')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function cv(Person $person): StreamedResponse
    {
        abort_if($person->cv_path === null || ! Storage::disk('local')->exists($person->cv_path), 404);

        return Storage::disk('local')->download($person->cv_path, $person->cv_name ?? 'cv');
    }

    public function storePhotos(Request $request, Person $person): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        foreach ($request->file('photos') as $file) {
            $person->photos()->create(['path' => $file->store('people', 'local')]);
        }

        return back();
    }

    public function showPhoto(PersonPhoto $photo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path);
    }

    /**
     * Makes a gallery photo the main one; the previous main photo joins the gallery.
     */
    public function makeMainPhoto(PersonPhoto $photo): RedirectResponse
    {
        $person = $photo->person;
        $previous = $person->photo_path;

        DB::transaction(function () use ($person, $photo, $previous) {
            $person->forceFill(['photo_path' => $photo->path])->save();
            $previous !== null
                ? $photo->forceFill(['path' => $previous])->saveQuietly()
                : $photo->deleteQuietly();
        });

        return back();
    }

    public function destroyPhoto(PersonPhoto $photo): RedirectResponse
    {
        $photo->delete();

        return back();
    }

    public function suggestAreas(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:100']]);

        return response()->json($this->areas->suggest($validated['q']));
    }

    private function storePhoto(PersonRequest $request, Person $person): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $this->deletePhoto($person);
        $person->forceFill(['photo_path' => $request->file('photo')->store('people', 'local')])->save();
    }

    private function storeCv(PersonRequest $request, Person $person): void
    {
        if (! $request->hasFile('cv')) {
            return;
        }

        $this->deleteCv($person);
        $file = $request->file('cv');
        $person->forceFill([
            'cv_path' => $file->store('people/cv', 'local'),
            'cv_name' => mb_strimwidth($file->getClientOriginalName(), 0, 255),
        ])->save();
    }

    private function deleteCv(Person $person): void
    {
        if ($person->cv_path !== null) {
            Storage::disk('local')->delete($person->cv_path);
            $person->forceFill(['cv_path' => null, 'cv_name' => null])->save();
        }
    }

    private function deletePhoto(Person $person): void
    {
        if ($person->photo_path !== null) {
            Storage::disk('local')->delete($person->photo_path);
            $person->forceFill(['photo_path' => null])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Person $person): array
    {
        return [
            'id' => $person->id,
            'name' => $person->name,
            'academic_title' => $person->academic_title,
            'affiliation' => $person->affiliation,
            'short_bio' => $person->short_bio,
            'email' => $person->email,
            'phone' => $person->phone,
            'photo_url' => $person->photo_path ? route('people.photo', $person, absolute: false).'?v='.$person->updated_at?->timestamp : null,
            'areas' => $person->areas->sortBy('name')->pluck('name')->values(),
            'needs_review' => $person->needsReview(),
            'keywords' => $person->keywords,
            'deceased_on' => $person->deceased_on?->toDateString(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function members(): array
    {
        $workspace = $this->context->get() ?? abort(403);

        return $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
