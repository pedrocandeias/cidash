<?php

namespace Tests\Feature\Assets;

use App\Enums\WorkspaceRole;
use App\Models\Asset;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
    }

    private function upload(array $files, array $data = []): TestResponse
    {
        return $this->actingAs($this->member)->post(route('assets.store'), ['files' => $files, ...$data]);
    }

    private function asset(string $title): Asset
    {
        app(WorkspaceContext::class)->set($this->workspace);

        return Asset::where('title', $title)->firstOrFail();
    }

    public function test_images_videos_and_graphics_are_uploaded_with_kind_size_and_thumbnail()
    {
        $this->upload([
            UploadedFile::fake()->image('Reitoria fachada.jpg', 1600, 1200),
            UploadedFile::fake()->create('Dia Aberto.mp4', 500, 'video/mp4'),
            UploadedFile::fake()->createWithContent('logotipo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ], ['category' => 'photos'])->assertSessionHasNoErrors();

        $photo = $this->asset('Reitoria fachada');
        $this->assertSame(['image', 1600, 1200, 'photos'], [$photo->kind, $photo->width, $photo->height, $photo->category]);
        $this->assertNotNull($photo->thumbnail_path);
        Storage::disk('local')->assertExists([$photo->path, $photo->thumbnail_path]);
        $this->assertSame('video', $this->asset('Dia Aberto')->kind);
        $this->assertSame('graphic', $this->asset('logotipo')->kind);

        $this->upload([UploadedFile::fake()->create('script.php', 1)])->assertSessionHasErrors('files.0');
    }

    public function test_one_upload_opens_its_page_to_add_caption_credit_category_and_tags()
    {
        $response = $this->upload([UploadedFile::fake()->image('foto.jpg')]);
        $asset = $this->asset('foto');
        $response->assertRedirect(route('assets.show', $asset->id));

        $this->actingAs($this->member)->patch(route('assets.update', $asset->id), [
            'title' => 'Receção aos novos estudantes', 'caption' => 'Estudantes no pátio da Reitoria.',
            'credit' => 'Egídio Santos', 'category' => 'photos', 'taken_on' => '2026-09-23', 'tags' => ['Estudantes', 'Reitoria'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->member)->get(route('assets.show', $asset->id))
            ->assertInertia(fn (Assert $page) => $page->component('assets/show')
                ->where('asset.caption', 'Estudantes no pátio da Reitoria.')
                ->where('asset.credit', 'Egídio Santos')
                ->where('asset.tags', ['Estudantes', 'Reitoria']));

        $this->actingAs($this->member)->patch(route('assets.update', $asset->id), ['category' => 'nope'])->assertSessionHasErrors('category');
    }

    public function test_the_gallery_is_searched_by_free_text_tag_category_and_type()
    {
        $this->upload([UploadedFile::fake()->image('a.jpg')]);
        $this->upload([UploadedFile::fake()->image('b.jpg')]);
        $this->upload([UploadedFile::fake()->create('c.mp4', 10, 'video/mp4')]);
        $this->actingAs($this->member)->patch(route('assets.update', $this->asset('a')->id), ['caption' => 'Cerimónia de doutoramento honoris causa', 'tags' => ['Cerimónias'], 'category' => 'photos']);
        $this->actingAs($this->member)->patch(route('assets.update', $this->asset('b')->id), ['credit' => 'Agência Lusa', 'tags' => ['Imprensa']]);

        $titles = fn (array $query) => $this->actingAs($this->member)->get(route('assets.index', $query))->viewData('page')['props']['assets'];

        $this->assertSame(['a'], array_column($titles(['q' => 'cerimonia doutor']), 'title'), 'accents and whole words are not needed');
        $this->assertSame(['b'], array_column($titles(['q' => 'lusa']), 'title'));
        $this->assertSame(['a'], array_column($titles(['tag' => 'Cerimónias']), 'title'));
        $this->assertSame(['a'], array_column($titles(['category' => 'photos']), 'title'));
        $this->assertSame(['c'], array_column($titles(['kind' => 'video']), 'title'));
        $this->actingAs($this->member)->get(route('assets.index'))->assertInertia(fn (Assert $page) => $page->where('collections', [['name' => 'Cerimónias', 'count' => 1], ['name' => 'Imprensa', 'count' => 1]]));
    }

    public function test_files_are_served_sandboxed_and_downloaded_with_their_name()
    {
        $this->upload([UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]);
        $asset = $this->asset('logo');

        $response = $this->actingAs($this->member)->get(route('assets.file', $asset->id))->assertOk();
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));

        $this->actingAs($this->member)->get(route('assets.file', ['asset' => $asset->id, 'download' => 1]))->assertDownload('logo.svg');
    }

    public function test_assets_are_isolated_and_deleting_removes_the_files()
    {
        $this->upload([UploadedFile::fake()->image('foto.jpg')]);
        $asset = $this->asset('foto');
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create(), WorkspaceRole::Manager)->create();

        $this->actingAs($outsider)->get(route('assets.file', $asset->id))->assertNotFound();
        $this->actingAs($outsider)->get(route('assets.show', $asset->id))->assertNotFound();

        $this->actingAs($this->member)->delete(route('assets.destroy', $asset->id))->assertRedirect(route('assets.index'));
        Storage::disk('local')->assertMissing([$asset->path, $asset->thumbnail_path]);
    }

    public function test_assets_are_gathered_in_collections_and_filtered_by_them()
    {
        $this->upload([UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')]);
        [$a, $b] = [$this->asset('a'), $this->asset('b')];

        $this->actingAs($this->member)
            ->post(route('assets.collection.store'), ['name' => 'Dia Aberto', 'assets' => [$a->id, $b->id]])
            ->assertSessionHasNoErrors();

        // Uploading while the collection is open adds the file to it.
        $this->upload([UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')], ['tags' => ['dia aberto']]);

        $this->actingAs($this->member)->get(route('assets.index', ['tag' => 'Dia Aberto']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets', 4)
                ->where('collections', [['name' => 'Dia Aberto', 'count' => 4]]));

        $this->actingAs($this->member)->delete(route('assets.collection.destroy'), ['name' => 'Dia Aberto', 'assets' => [$a->id]]);
        $this->actingAs($this->member)->get(route('assets.index', ['tag' => 'Dia Aberto']))
            ->assertInertia(fn (Assert $page) => $page->has('assets', 3));
    }

    public function test_assets_of_another_team_cannot_be_added_to_a_collection()
    {
        $this->upload([UploadedFile::fake()->image('nossa.jpg')]);
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create())->create();

        $this->actingAs($outsider)->post(route('assets.collection.store'), ['name' => 'Roubada', 'assets' => [$this->asset('nossa')->id]]);

        $this->assertSame([], $this->asset('nossa')->record->tags->pluck('name')->all());
    }
}
