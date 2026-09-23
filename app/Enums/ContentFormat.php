<?php

namespace App\Enums;

enum ContentFormat: string
{
    case News = 'news';
    case Article = 'article';
    case PressRelease = 'press_release';
    case SocialPost = 'social_post';
    case Video = 'video';
    case Photos = 'photos';
    case Newsletter = 'newsletter';
    case Podcast = 'podcast';
    case Other = 'other';
}
