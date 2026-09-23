<?php

namespace Tests\Feature\Core;

use App\Core\Scopes\WorkspaceScope;
use App\Models\Activity;
use App\Models\Record;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Fixtures\TestNote;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $reitoria;

    private Workspace $feup;

    protected function setUp(): void
    {
        parent::setUp();

        TestNote::setUpTable();
        $this->reitoria = Workspace::factory()->create();
        $this->feup = Workspace::factory()->create();
    }

    private function in(Workspace $workspace): void
    {
        app(WorkspaceContext::class)->set($workspace);
    }

    public function test_creating_a_domain_model_creates_its_record()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->in($this->reitoria);

        $note = TestNote::create(['title' => 'Dia Aberto']);

        $record = Record::findOrFail($note->id);
        $this->assertSame($this->reitoria->id, $record->workspace_id);
        $this->assertSame('test_note', $record->type);
        $this->assertSame('Dia Aberto', $record->title);
        $this->assertSame($user->id, $record->created_by);
        $this->assertTrue($record->subject->is($note));
    }

    public function test_the_title_is_kept_in_sync_and_changes_are_logged()
    {
        $this->in($this->reitoria);
        $note = TestNote::create(['title' => 'Rascunho']);

        $note->update(['title' => 'Versão final']);

        $this->assertSame('Versão final', $note->record->refresh()->title);
        $this->assertSame(['created', 'updated'], $note->record->activity()->orderBy('id')->pluck('action')->all());
        $this->assertSame(['from' => 'Rascunho', 'to' => 'Versão final'], $note->record->activity()->latest('id')->first()->changes['title']);
    }

    public function test_deleting_the_model_deletes_the_record_but_keeps_the_audit_trail()
    {
        $this->in($this->reitoria);
        $note = TestNote::create(['title' => 'Apagar']);
        $id = $note->id;

        $note->delete();

        $this->assertDatabaseMissing('objects', ['id' => $id]);
        $this->assertSame('deleted', Activity::latest('id')->first()->action);
        $this->assertNull(Activity::latest('id')->first()->object_id);
    }

    public function test_workspaces_are_isolated()
    {
        $this->in($this->reitoria);
        $ours = TestNote::create(['title' => 'Da Reitoria']);
        $this->in($this->feup);
        $theirs = TestNote::create(['title' => 'Da FEUP']);

        $this->in($this->reitoria);
        $this->assertSame([$ours->id], TestNote::pluck('id')->all());
        $this->assertSame([$ours->id], Record::pluck('id')->all());
        $this->assertNull(TestNote::find($theirs->id));

        $this->assertCount(2, TestNote::withoutGlobalScope(WorkspaceScope::class)->get());
    }

    public function test_queries_without_a_workspace_fail_instead_of_leaking()
    {
        $this->expectException(LogicException::class);

        TestNote::count();
    }
}
