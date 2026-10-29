<?php

namespace App\Support;

use App\Enums\AttachmentKind;
use App\Enums\AuditEvent;
use App\Enums\PageType;
use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Notation;
use App\Models\Page;

class AppMeta
{
    /**
     * Static reference data clients need to render and validate: enum options, limits and defaults.
     * Shared with the web app as Inertia props and served to API clients by `GET /api/v1/meta`.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'enums' => [
                'spaceVisibility' => self::options(SpaceVisibility::cases(), withDescription: true),
                'spaceRole' => self::options(SpaceRole::cases(), withDescription: true),
                'pageType' => self::options(PageType::cases()),
                'auditEvent' => self::options(AuditEvent::cases()),
                'userRole' => [['value' => 'member', 'label' => 'Member'], ['value' => 'admin', 'label' => 'Admin']],
            ],
            'attachments' => AttachmentKind::config(),
            'attachmentKinds' => array_map(
                fn (AttachmentKind $kind) => ['value' => $kind->value, 'label' => $kind->filterLabel()],
                AttachmentKind::cases(),
            ),
            'tokenScopes' => ApiScopes::options(),
            'untitledTitle' => Page::UNTITLED,
            'trashRetentionDays' => Page::TRASH_RETENTION_DAYS,
            'notationBodyMax' => Notation::BODY_MAX,
        ];
    }

    /**
     * @param  list<SpaceVisibility|SpaceRole|PageType|AuditEvent>  $cases
     * @return list<array{value: string, label: string, description?: string}>
     */
    private static function options(array $cases, bool $withDescription = false): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            ...($withDescription ? ['description' => $case->description()] : []),
        ], $cases);
    }
}
