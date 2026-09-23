<?php

namespace App\Enums;

enum EventType: string
{
    case Institutional = 'institutional';
    case Campaign = 'campaign';
    case Publication = 'publication';
    case Ephemeris = 'ephemeris';
    case Deadline = 'deadline';
}
