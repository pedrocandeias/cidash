<?php

namespace Tests\Feature\Core;

use App\Enums\WorkspaceRole;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        app(WorkspaceContext::class)->set($this->workspace);
        $this->task = Task::create(['title' => 'Kit de imprensa', 'priority' => 'normal', 'status' => 'todo']);
    }

    private function upload(User $user, array $files): TestResponse
    {
        return $this->actingAs($user)->post(route('attachments.store', $this->task->id), ['files' => $files]);
    }

    public function test_files_are_attached_to_any_record_and_listed_on_its_page()
    {
        $this->upload($this->member, [
            UploadedFile::fake()->create('comunicado.pdf', 120, 'application/pdf'),
            UploadedFile::fake()->image('foto.jpg'),
        ])->assertSessionHasNoErrors();

        $attachment = Attachment::where('original_name', 'comunicado.pdf')->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringStartsWith("attachments/{$this->workspace->id}/", $attachment->path);

        $this->actingAs($this->member)->get(route('tasks.show', $this->task->id))
            ->assertInertia(fn (Assert $page) => $page->has('attachments', 2)->where('attachments.1.name', 'comunicado.pdf')->where('attachments.1.can_delete', true));

        $this->actingAs($this->member)->get(route('attachments.show', $attachment->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_dangerous_or_oversized_files_are_refused()
    {
        $this->upload($this->member, [UploadedFile::fake()->create('script.php', 1)])->assertSessionHasErrors('files.0');
        $this->upload($this->member, [UploadedFile::fake()->create('pagina.html', 1, 'text/html')])->assertSessionHasErrors('files.0');
        $this->upload($this->member, [UploadedFile::fake()->create('enorme.pdf', 30000, 'application/pdf')])->assertSessionHasErrors('files.0');

        $this->assertSame(0, Attachment::count());
    }

    public function test_svg_is_downloaded_not_rendered()
    {
        $this->upload($this->member, [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]);

        $response = $this->actingAs($this->member)->get(route('attachments.show', Attachment::sole()->id))->assertOk();

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_only_the_uploader_or_a_manager_removes_and_the_file_goes_with_it()
    {
        $colleague = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $this->upload($this->member, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]);
        [$first, $second] = Attachment::orderBy('id')->get()->all();

        $this->actingAs($colleague)->delete(route('attachments.destroy', $first->id))->assertForbidden();
        $this->actingAs($this->member)->delete(route('attachments.destroy', $first->id))->assertRedirect();
        $this->actingAs($manager)->delete(route('attachments.destroy', $second->id))->assertRedirect();

        $this->assertSame(0, Attachment::count());
        Storage::disk('local')->assertMissing([$first->path, $second->path]);
    }

    public function test_deleting_the_record_removes_its_files()
    {
        $this->upload($this->member, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $path = Attachment::sole()->path;

        $this->task->delete();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, Attachment::count());
    }

    public function test_other_teams_cannot_attach_or_read()
    {
        $this->upload($this->member, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $id = Attachment::sole()->id;
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create(), WorkspaceRole::Manager)->create();

        $this->actingAs($outsider)->get(route('attachments.show', $id))->assertNotFound();
        $this->actingAs($outsider)->delete(route('attachments.destroy', $id))->assertNotFound();
        $this->upload($outsider, [UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])->assertNotFound();
    }
}
