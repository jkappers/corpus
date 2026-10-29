<?php

namespace App\Enums;

enum SpaceVisibility: string
{
    case Open = 'open';
    case Visible = 'visible';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Visible => 'Visible',
            self::Private => 'Private',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Open => 'Everyone can read and edit.',
            self::Visible => 'Everyone can read. Only members can edit.',
            self::Private => 'Only members can see it.',
        };
    }
}
