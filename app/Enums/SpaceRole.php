<?php

namespace App\Enums;

enum SpaceRole: string
{
    case Manager = 'manager';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Manager => 'Changes settings and members.',
            self::Editor => 'Creates and edits pages.',
            self::Viewer => 'Reads only.',
        };
    }

    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }
}
