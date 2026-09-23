<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';
}
