<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Attachment;
use App\Models\Record;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files attached to any record of the current workspace (ARCHITECTURE.md §2.3).
 */
class AttachmentController extends Controller
{
    /** Kilobytes. */
    public const MAX_SIZE = 20480;

    private const TYPES = 'jpg,jpeg,png,gif,webp,svg,heic,pdf,doc,docx,odt,rtf,txt,md,xls,xlsx,ods,csv,ppt,pptx,odp,zip,mp3,m4a,wav,mp4,mov';

    public function store(Request $request, Record $record): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.self::MAX_SIZE, 'extensions:'.self::TYPES],
        ]);

        foreach ($request->file('files') as $file) {
            $record->attachments()->create([
                'user_id' => $request->user()->id,
                'original_name' => mb_strimwidth($file->getClientOriginalName(), 0, 255),
                'path' => $file->store("attachments/{$record->workspace_id}", 'local'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back();
    }

    public function show(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // Images and PDFs open in the browser; everything else downloads.
        $inline = in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $inline ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Request $request, Attachment $attachment, WorkspaceContext $context): RedirectResponse
    {
        $user = $request->user();
        $workspace = $context->get() ?? abort(403);

        abort_unless($attachment->user_id === $user->id || $user->hasRole($workspace, WorkspaceRole::Manager), 403);

        $attachment->delete();

        return back();
    }
}
