<?php

namespace App\Enums;

use Illuminate\Validation\ValidationException;

enum PageType: string
{
    case Page = 'page';
    case Board = 'board';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Page',
            self::Board => 'Board',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Page => 'Pages',
            self::Board => 'Boards',
        };
    }

    /**
     * Pages hold markdown in `body`; boards hold canvas state in `data`. Each refuses the other's content.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function ensureAcceptsContent(array $attributes): void
    {
        $foreign = $this === self::Page ? 'data' : 'body';

        if (($attributes[$foreign] ?? null) !== null) {
            throw ValidationException::withMessages([
                $foreign => $this === self::Page ? 'Pages have no board data.' : 'Boards have no markdown body.',
            ]);
        }
    }
}
