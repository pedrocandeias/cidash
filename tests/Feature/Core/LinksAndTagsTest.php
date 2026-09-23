<?php

namespace Tests\Feature\Core;

use App\Core\Links;
use App\Core\Tags;
use App\Core\Terms;
use App\Enums\RelationType;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Fixtures\TestNote;
use Tests\TestCase;

class LinksAndTagsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $reitoria;

    protected function setUp(): void
    {
        parent::setUp();

        TestNote::setUpTable();
        $this->reitoria = Workspace::factory()->create();
        $this->in($this->reitoria);
    }

    private function in(Workspace $workspace): void
    {
        app(WorkspaceContext::class)->set($workspace);
    }

    public function test_records_are_linked_and_seen_from_both_ends()
    {
        $event = TestNote::create(['title' => 'Dia Aberto']);
        $campaign = TestNote::create(['title' => 'Candidaturas 2027']);
        $links = app(Links::class);

        $links->link($event, $campaign, RelationType::PartOf);
        $links->link($event, $campaign, RelationType::PartOf);

        $fromEvent = $links->of($event);
        $this->assertCount(1, $fromEvent);
        $this->assertTrue($fromEvent[0]['other']->is($campaign->record));
        $this->assertTrue($fromEvent[0]['outgoing']);

        $fromCampaign = $links->of($campaign);
        $this->assertTrue($fromCampaign[0]['other']->is($event->record));
        $this->assertFalse($fromCampaign[0]['outgoing']);

        $links->unlink($event, $campaign, RelationType::PartOf);
        $this->assertCount(0, $links->of($event));
    }

    public function test_records_of_different_workspaces_cannot_be_linked()
    {
        $ours = TestNote::create(['title' => 'Nosso']);
        $this->in(Workspace::factory()->create());
        $theirs = TestNote::create(['title' => 'Deles']);

        $this->expectException(InvalidArgumentException::class);

        app(Links::class)->link($ours, $theirs);
    }

    public function test_terms_are_normalized()
    {
        $this->assertSame('astronomia', Terms::normalize('  ASTRONOMÍA '));
        $this->assertSame('saude publica', Terms::normalize('Saúde   Pública'));
        $this->assertTrue(Terms::similar('Astronomia', 'Astrnomia'));
        $this->assertFalse(Terms::similar('Astronomia', 'astronomia'));
        $this->assertFalse(Terms::similar('Astronomia', 'Biologia'));
    }

    public function test_tags_are_never_duplicated_within_a_workspace()
    {
        $tags = app(Tags::class);

        $first = $tags->findOrCreate('Astronomia');
        $this->assertTrue($tags->findOrCreate(' astronomia ')->is($first));
        $this->assertTrue($tags->findOrCreate('ASTRONOMÍA')->is($first));
        $this->assertSame('Astronomia', $first->name);
        $this->assertSame(1, Tag::count());

        $this->in(Workspace::factory()->create());
        $this->assertFalse($tags->findOrCreate('Astronomia')->is($first));
    }

    public function test_similar_tags_are_suggested()
    {
        $tags = app(Tags::class);
        $tags->findOrCreate('Astronomia');
        $tags->findOrCreate('Biologia');

        $this->assertSame(['Astronomia'], $tags->similarTo('Astrnomia')->pluck('name')->all());
    }

    public function test_tags_are_synced_on_a_record()
    {
        $note = TestNote::create(['title' => 'Noite Europeia dos Investigadores']);

        app(Tags::class)->sync($note->record, ['Ciência', 'ciencia', 'Eventos', ' ']);

        $this->assertSame(['Ciência', 'Eventos'], $note->record->tags()->orderBy('name')->pluck('name')->all());
    }

    public function test_comments_follow_the_workspace_of_their_record()
    {
        $note = TestNote::create(['title' => 'Com comentários']);
        $comment = $note->record->comments()->create(['body' => 'Falta a fotografia.']);

        $this->in(Workspace::factory()->create());

        $this->assertNull(Comment::find($comment->id));
    }
}
