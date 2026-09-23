<?php

namespace App\Notifications;

use App\Models\Alert;

class AlertRaised extends CidashNotification
{
    public function __construct(Alert $alert)
    {
        parent::__construct([
            'message' => $alert->message,
            'title' => $alert->title,
            'by' => null,
            'workspace_id' => $alert->workspace_id,
            'url' => route('alerts.index', absolute: false),
        ]);
    }

    public function kind(): string
    {
        return 'alerts';
    }
}
