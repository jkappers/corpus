<?php

namespace App\Support;

use App\Models\User;

class ApiScopes
{
    /**
     * Scopes a REST API token can carry. The MCP scope is separate: it is only issued through OAuth.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'profile:read' => 'Read your profile and API reference data',
            'pages:read' => 'Read pages, boards and versions',
            'pages:write' => 'Create, edit, trash and restore pages and boards',
            'notations:read' => 'Read notes and your inbox',
            'notations:write' => 'Create, edit, complete and delete notes',
            'spaces:read' => 'Read spaces and their members',
            'spaces:write' => 'Create and manage spaces and members',
            'attachments:read' => 'Read and download files',
            'attachments:write' => 'Upload files',
            'admin:read' => 'Read users and the audit log (administrators)',
            'admin:write' => 'Manage users (administrators)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return list<string>
     */
    public static function availableTo(User $user): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $scope) => $user->is_admin || ! str_starts_with($scope, 'admin:'),
        ));
    }

    /**
     * @return list<array{value: string, label: string, admin: bool}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::definitions() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label, 'admin' => str_starts_with($value, 'admin:')];
        }

        return $options;
    }
}
