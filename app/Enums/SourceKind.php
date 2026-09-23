<?php

namespace App\Enums;

enum SourceKind: string
{
    case Rss = 'rss';
    case GoogleNews = 'google_news';
    case Scraper = 'scraper';
}
