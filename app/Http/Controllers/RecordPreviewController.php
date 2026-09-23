<?php

namespace App\Http\Controllers;

use App\Core\RecordPreview;
use App\Models\Record;
use Illuminate\Http\JsonResponse;

/**
 * JSON for the side drawer that previews any record of the current workspace.
 */
class RecordPreviewController extends Controller
{
    public function __invoke(Record $record): JsonResponse
    {
        return response()->json(RecordPreview::for($record));
    }
}
