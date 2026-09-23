<?php

namespace Tests\Feature\Search;

use App\Models\CalendarEvent;
use App\Models\Person;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->ana = User::factory()->inWorkspace($this->workspace)->create();
    }

    private function in(Workspace $workspace): void
    {
        app(WorkspaceContext::class)->set($workspace);
        $this->actingAs($this->ana);
    }

    private function search(string $q, array $extra = []): array
    {
        return $this->actingAs($this->ana)->getJson(route('records.search', ['q' => $q, ...$extra]))->assertOk()->json();
    }

    public function test_it_finds_records_of_every_type_ignoring_accents_and_case()
    {
        $this->in($this->workspace);
        Person::create(['name' => 'Ana Sousa', 'short_bio' => 'Estuda a formação de galáxias.']);
        CalendarEvent::create(['title' => 'Noite das Galáxias', 'type' => 'institutional', 'start_at' => now(), 'priority' => 'normal', 'status' => 'confirmed']);
        Task::create(['title' => 'Outra coisa', 'priority' => 'normal', 'status' => 'todo']);

        $results = $this->search('GALAXIAS');

        $this->assertEqualsCanonicalizing(['Ana Sousa', 'Noite das Galáxias'], array_column($results, 'title'));
        $this->assertEqualsCanonicalizing(['person', 'event'], array_column($results, 'type'));
    }

    public function test_words_match_as_prefixes_and_titles_rank_first()
    {
        $this->in($this->workspace);
        Task::create(['title' => 'Rever texto', 'description' => 'Sobre investigação em saúde', 'priority' => 'normal', 'status' => 'todo']);
        Task::create(['title' => 'Investigação em saúde', 'priority' => 'normal', 'status' => 'todo']);

        $this->assertSame(['Investigação em saúde', 'Rever texto'], array_column($this->search('invest saud'), 'title'));
    }

    public function test_the_index_follows_updates_and_deletions()
    {
        $this->in($this->workspace);
        $task = Task::create(['title' => 'Relatório anual', 'priority' => 'normal', 'status' => 'todo']);

        $task->update(['title' => 'Relatório de atividades']);
        $this->assertSame([], $this->search('anual'));
        $this->assertCount(1, $this->search('atividades'));

        $this->in($this->workspace);
        $task->delete();
        $this->assertSame([], $this->search('atividades'));
    }

    public function test_other_workspaces_are_never_searched()
    {
        $other = Workspace::factory()->create();
        $this->in($other);
        Task::create(['title' => 'Segredo da outra equipa', 'priority' => 'normal', 'status' => 'todo']);

        $this->assertSame([], $this->search('segredo'));
    }

    public function test_special_characters_do_not_break_the_query()
    {
        $this->in($this->workspace);
        Task::create(['title' => 'Plano "2027" (versão A)', 'priority' => 'normal', 'status' => 'todo']);

        $this->assertCount(1, $this->search('"2027" (versão*'));
        // FTS operators typed by users are searched as plain words, never interpreted.
        $this->assertSame([], $this->search('plano NOT versão'));
        $this->assertSame([], $this->search('** ""'));
    }

    public function test_the_index_can_be_rebuilt()
    {
        $this->in($this->workspace);
        Task::create(['title' => 'Reindexar isto', 'priority' => 'normal', 'status' => 'todo']);

        $this->artisan('cidash:search-reindex')->expectsOutputToContain('Indexed 1 records')->assertSuccessful();
        $this->assertCount(1, $this->search('reindexar'));
    }
}
