<?php

namespace Tests\Feature\People;

use App\Core\Tags;
use App\Enums\WorkspaceRole;
use App\Models\ExpertiseArea;
use App\Models\Person;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace)->create();
    }

    private function person(array $attributes = [], array $areas = []): Person
    {
        $this->actingAs($this->member)->post(route('people.store'), ['name' => 'Pessoa', ...$attributes, 'areas' => $areas]);
        app(WorkspaceContext::class)->set($this->workspace);

        return Person::where('name', $attributes['name'] ?? 'Pessoa')->firstOrFail();
    }

    public function test_a_profile_is_created_with_areas_that_are_never_duplicated()
    {
        $ana = $this->person(['name' => 'Ana Sousa', 'affiliation' => 'FCUP', 'short_bio' => 'Astrofísica.'], ['Astronomia', ' astronomia ', 'Espaço']);
        $rui = $this->person(['name' => 'Rui Lopes'], ['ASTRONOMÍA']);

        $this->assertSame(['Astronomia', 'Espaço'], $ana->areas()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Astronomia'], $rui->areas->pluck('name')->all());
        $this->assertSame(2, ExpertiseArea::count());
        $this->assertFalse($ana->needsReview());
    }

    public function test_people_are_found_by_keyword_or_area()
    {
        $this->person(['name' => 'Ana Sousa', 'keywords' => 'galáxias, telescópios'], ['Astronomia']);
        $this->person(['name' => 'Rui Lopes', 'bio' => 'Especialista em saúde pública.'], ['Saúde']);
        $area = ExpertiseArea::where('name', 'Saúde')->firstOrFail();

        $this->actingAs($this->member)->get(route('people.index', ['q' => 'telescóp']))
            ->assertInertia(fn (Assert $page) => $page->component('people/index')->has('people', 1)->where('people.0.name', 'Ana Sousa'));

        $this->actingAs($this->member)->get(route('people.index', ['area' => $area->id]))
            ->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.name', 'Rui Lopes')->has('areas', 2));
    }

    public function test_photos_are_private_to_the_team()
    {
        Storage::fake('local');
        $person = $this->person(['name' => 'Com foto', 'photo' => UploadedFile::fake()->image('foto.jpg')]);

        $this->assertNotNull($person->photo_path);
        Storage::disk('local')->assertExists($person->photo_path);
        $this->actingAs($this->member)->get(route('people.photo', $person))->assertOk();

        $outsider = User::factory()->inWorkspace()->create();
        $this->actingAs($outsider)->get(route('people.photo', $person))->assertNotFound();
        $this->actingAs($outsider)->get(route('people.show', $person))->assertNotFound();
    }

    public function test_managers_rename_and_merge_terms()
    {
        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $ana = $this->person(['name' => 'Ana'], ['Astronomia']);
        $rui = $this->person(['name' => 'Rui'], ['Astrofisica']);
        $astronomia = ExpertiseArea::where('name', 'Astronomia')->firstOrFail();
        $astrofisica = ExpertiseArea::where('name', 'Astrofisica')->firstOrFail();

        $this->actingAs($this->member)->patch(route('terms.update', ['areas', $astrofisica->id]), ['name' => 'Astrofísica'])->assertForbidden();

        $this->actingAs($manager)->patch(route('terms.update', ['areas', $astrofisica->id]), ['name' => 'Astrofísica']);
        $this->assertSame('Astrofísica', $astrofisica->refresh()->name);

        // Renaming to an existing name merges.
        $this->actingAs($manager)->patch(route('terms.update', ['areas', $astrofisica->id]), ['name' => 'astronomia']);
        $this->assertNull(ExpertiseArea::find($astrofisica->id));
        $this->assertSame(['Astronomia'], $rui->areas()->pluck('name')->all());
        $this->assertSame(['Astronomia'], $ana->areas()->pluck('name')->all());

        $this->actingAs($manager)->get(route('terms.index'))
            ->assertInertia(fn (Assert $page) => $page->component('settings/terms')->where('areas.0.usage', 2));
    }

    public function test_merging_tags_keeps_one_link_per_record()
    {
        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        app(WorkspaceContext::class)->set($this->workspace);
        $this->actingAs($manager);
        $person = Person::create(['name' => 'Com tags']);
        app(Tags::class)->sync($person->record, ['Ciência', 'Ciencia 2']);
        $keep = Tag::where('name', 'Ciência')->firstOrFail();
        $drop = Tag::where('name', 'Ciencia 2')->firstOrFail();

        $this->actingAs($manager)->post(route('terms.merge', ['tags', $drop->id]), ['into' => $keep->id]);

        $this->assertSame(['Ciência'], $person->record->tags()->pluck('name')->all());
        $this->assertSame(1, Tag::count());
    }
}
