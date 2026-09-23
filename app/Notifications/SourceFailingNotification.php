<?php

namespace App\Notifications;

use App\Models\Source;

/**
 * For super admins, who maintain the sources catalogue.
 */
class SourceFailingNotification extends CidashNotification
{
    public function __construct(Source $source)
    {
        parent::__construct([
            'message' => 'Source failing',
            'title' => $source->name,
            'by' => null,
            'workspace_id' => null,
            'url' => route('admin.sources.index', absolute: false),
        ]);
    }

    public function kind(): string
    {
        return 'alerts';
    }
}
