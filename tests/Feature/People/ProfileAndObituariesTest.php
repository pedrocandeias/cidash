<?php

namespace Tests\Feature\People;

use App\Enums\WorkspaceRole;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileAndObituariesTest extends TestCase
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
        app(WorkspaceContext::class)->set($this->workspace);
    }

    public function test_the_profile_shows_career_topics_cv_and_photos()
    {
        $person = Person::create(['name' => 'Helena Vasconcelos', 'keywords' => 'galáxias, exoplanetas', 'career' => "2020 – Professora Associada\n2012 – Doutoramento"]);

        $this->actingAs($this->member)->patch(route('people.update', $person->id), [
            'cv' => UploadedFile::fake()->create('CV Helena.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->member)->post(route('people.photos.store', $person->id), [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->member)->get(route('people.show', $person->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('person.career', "2020 – Professora Associada\n2012 – Doutoramento")
                ->where('person.keywords', 'galáxias, exoplanetas')
                ->where('person.cv.name', 'CV Helena.pdf')
                ->has('person.photos', 2));

        $this->actingAs($this->member)->get(route('people.cv', $person->id))
            ->assertOk()->assertDownload('CV Helena.pdf');

        $this->actingAs($this->member)->patch(route('people.update', $person->id), ['cv' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('cv');
    }

    public function test_a_gallery_photo_becomes_the_main_one_and_photos_are_removed_with_their_files()
    {
        $person = Person::create(['name' => 'Rui Magalhães']);
        $this->actingAs($this->member)->post(route('people.photos.store', $person->id), ['photos' => [UploadedFile::fake()->image('a.jpg')]]);
        $photo = PersonPhoto::sole();
        $path = $photo->path;

        $this->actingAs($this->member)->post(route('people.photos.main', $photo->id))->assertRedirect();
        $this->assertSame($path, $person->refresh()->photo_path);
        $this->assertSame(0, PersonPhoto::count(), 'with no previous main photo the gallery entry goes');

        $this->actingAs($this->member)->post(route('people.photos.store', $person->id), ['photos' => [UploadedFile::fake()->image('b.jpg')]]);
        $second = PersonPhoto::sole();
        $this->actingAs($this->member)->post(route('people.photos.main', $second->id));
        $this->assertSame($path, $second->refresh()->path, 'the previous main photo joins the gallery');

        $this->actingAs($this->member)->delete(route('people.photos.destroy', $second->id))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_dead_or_alive_lists_obituaries_and_the_deceased_leave_the_experts()
    {
        Person::create(['name' => 'Helena Vasconcelos']);
        $prepared = Person::create(['name' => 'Rui Magalhães', 'obituary' => 'Obituário preparado.']);
        $deceased = Person::create(['name' => 'Joaquim Sarmento', 'obituary' => 'Foi uma referência.', 'deceased_on' => now()->subDay()->toDateString()]);
        $this->assertNotNull($prepared->obituary_updated_at);

        $this->actingAs($this->member)->get(route('people.index'))
            ->assertInertia(fn (Assert $page) => $page->has('people', 2)->where('people.0.name', 'Helena Vasconcelos'));

        $this->actingAs($this->member)->get(route('people.obituaries'))
            ->assertInertia(fn (Assert $page) => $page->component('people/obituaries')
                ->has('people', 2)
                ->where('people.0.name', 'Joaquim Sarmento')
                ->where('people.1.name', 'Rui Magalhães')
                ->where('candidates', [['id' => Person::where('name', 'Helena Vasconcelos')->value('id'), 'name' => 'Helena Vasconcelos']]));
        $this->actingAs($this->member)->get(route('people.obituaries', ['filter' => 'deceased']))
            ->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.deceased_on', $deceased->deceased_on->toDateString()));

        $this->actingAs($this->member)->patch(route('people.update', $prepared->id), ['deceased_on' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('deceased_on');
    }

    public function test_files_and_photos_of_other_teams_are_not_reachable()
    {
        $person = Person::create(['name' => 'Helena Vasconcelos']);
        $this->actingAs($this->member)->patch(route('people.update', $person->id), ['cv' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]);
        $this->actingAs($this->member)->post(route('people.photos.store', $person->id), ['photos' => [UploadedFile::fake()->image('a.jpg')]]);
        $photoId = PersonPhoto::sole()->id;
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create(), WorkspaceRole::Manager)->create();

        $this->actingAs($outsider)->get(route('people.cv', $person->id))->assertNotFound();
        $this->actingAs($outsider)->get(route('people.photos.show', $photoId))->assertNotFound();
        $this->actingAs($outsider)->delete(route('people.photos.destroy', $photoId))->assertNotFound();
    }
}
