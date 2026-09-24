<?php

namespace App\Http\Controllers;

use App\Assets\AssetFiles;
use App\Core\RecordPage;
use App\Core\Search\Search;
use App\Core\Tags;
use App\Models\Asset;
use App\Models\Tag;
use App\Support\Options;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Assets: the team's gallery of images, videos and graphics, with categories,
 * captions, tags and search.
 */
class AssetController extends Controller
{
    public function __construct(private WorkspaceContext $context, private Tags $tags) {}

    public function index(Request $request, Search $search): Response
    {
        $workspace = $this->context->get() ?? abort(403);
        $filters = [
            'q' => trim((string) $request->query('q')),
            'category' => (string) $request->query('category'),
            'kind' => in_array($request->query('kind'), ['image', 'video', 'graphic'], true) ? (string) $request->query('kind') : '',
            'tag' => trim((string) $request->query('tag')),
        ];

        $assets = Asset::query()
            ->with('record.tags')
            ->when($filters['q'] !== '', fn ($query) => $query->whereKey($search->search($workspace->id, $filters['q'], 500)->pluck('id')))
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['kind'] !== '', fn ($query) => $query->where('kind', $filters['kind']))
            ->when($filters['tag'] !== '', fn ($query) => $query->whereHas('record.tags', fn ($query) => $query->where('name', $filters['tag'])))
            ->latest()
            ->limit(300)
            ->get();

        return Inertia::render('assets/index', [
            'assets' => $assets->map(fn (Asset $asset) => $this->summary($asset)),
            'filters' => $filters,
            // Tags used by the team's assets, for the filter.
            'tags' => Tag::whereIn('id', DB::table('object_tag')
                ->join('objects', 'objects.id', '=', 'object_tag.object_id')
                ->where('objects.type', 'asset')
                ->where('objects.workspace_id', $workspace->id)
                ->select('object_tag.tag_id'))
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function store(Request $request, AssetFiles $files, Options $options): RedirectResponse
    {
        $workspace = $this->context->get() ?? abort(403);
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.AssetFiles::MAX_SIZE, 'extensions:'.implode(',', [...AssetFiles::IMAGES, ...AssetFiles::VIDEOS, ...AssetFiles::GRAPHICS])],
            'category' => ['nullable', Rule::in($options->keys('asset_category'))],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $created = [];
        foreach ($request->file('files') as $file) {
            $asset = $files->store($file, $workspace->id, ['category' => $request->input('category')]);
            $this->tags->sync($asset->record, $request->input('tags', []));
            $created[] = $asset;
        }

        // One file opens its page to be described; several go back to the gallery.
        return count($created) === 1 ? to_route('assets.show', $created[0]) : back();
    }

    public function show(Request $request, Asset $asset, RecordPage $page): Response
    {
        $asset->load('record.tags');

        return Inertia::render('assets/show', [
            'asset' => [
                ...$this->summary($asset),
                'caption' => $asset->caption,
                'credit' => $asset->credit,
                'taken_on' => $asset->taken_on?->toDateString(),
                'original_name' => $asset->original_name,
                'mime_type' => $asset->mime_type,
                'size' => $asset->size,
                'download_url' => route('assets.file', ['asset' => $asset, 'download' => 1], absolute: false),
            ],
            ...$page->for($asset->record, $request->user()),
            'can' => ['delete' => $request->user()->can('delete', $asset)],
        ]);
    }

    public function update(Request $request, Asset $asset, Options $options): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', Rule::in($options->keys('asset_category'))],
            'taken_on' => ['sometimes', 'nullable', 'date'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ]);

        unset($validated['tags']);
        $asset->update($validated);
        if ($request->has('tags')) {
            $this->tags->sync($asset->record, $request->input('tags', []));
        }

        return back();
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);
        $asset->delete();

        return to_route('assets.index');
    }

    /**
     * The file itself (videos can be scrubbed: range requests are supported). Served
     * sandboxed, so an SVG opened on its own cannot run scripts.
     */
    public function file(Request $request, Asset $asset): BinaryFileResponse
    {
        return $this->serve($asset->path, $request->boolean('download') ? $asset->original_name : null);
    }

    public function thumbnail(Asset $asset): BinaryFileResponse
    {
        return $this->serve($asset->thumbnail_path ?? $asset->path, null);
    }

    private function serve(string $path, ?string $downloadName): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);

        if ($downloadName !== null) {
            $response->setContentDisposition('attachment', $downloadName, Str::ascii($downloadName));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Asset $asset): array
    {
        $version = '?v='.$asset->updated_at?->timestamp;

        return [
            'id' => $asset->id,
            'title' => $asset->title,
            'kind' => $asset->kind,
            'category' => $asset->category,
            'caption' => $asset->caption,
            'credit' => $asset->credit,
            'width' => $asset->width,
            'height' => $asset->height,
            'tags' => $asset->record->tags->sortBy('name')->pluck('name')->values(),
            'url' => route('assets.file', $asset, absolute: false),
            'thumbnail_url' => route('assets.thumbnail', $asset, absolute: false).$version,
            // Browsers cannot show EPS, AI, PDF, HEIC or TIFF as an image.
            'previewable' => $asset->thumbnail_path !== null
                || in_array(strtolower(pathinfo($asset->original_name, PATHINFO_EXTENSION)), ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true),
        ];
    }
}
