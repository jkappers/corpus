<?php

namespace App\Http\Controllers\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Mcp\Server\Registrar;

/**
 * Dynamic client registration (RFC 7591) with the MCP spec's redirect URI rules:
 * HTTPS, or http on a loopback host. Private-use schemes are allowed only when listed in `mcp.custom_schemes`.
 */
class RegisterClientController extends OAuthRegisterController
{
    /**
     * Self-registered clients may only ever request the MCP scope, never the API scopes.
     */
    protected function grantMcpScope(mixed $client): void
    {
        if ($client instanceof Model) {
            $client->forceFill(['scopes' => [Registrar::OAUTH_SCOPE]])->save();
        }
    }

    protected function isValidRedirectUri(string $value): bool
    {
        $parts = parse_url($value);

        if ($parts === false || isset($parts['fragment']) || str_contains($value, '#')) {
            return false;
        }

        return match ($parts['scheme'] ?? null) {
            'https' => ! isset($parts['user'], $parts['pass']) && Str::isUrl($value, ['https']),
            'http' => $this->isLocalhostUrl($value),
            default => parent::isValidRedirectUri($value),
        };
    }
}
