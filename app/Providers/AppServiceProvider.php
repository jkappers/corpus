<?php

namespace App\Providers;

use App\Enums\PageType;
use App\Models\OAuthClient;
use App\Models\Page;
use App\Models\User;
use App\Support\ApiScopes;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureResources();
        $this->configureRateLimiting();
        $this->configurePassport();
        $this->configureApiDocs();
        $this->configureRouteBindings();
    }

    /**
     * Pages and boards share a table, but routes treat them as separate entities:
     * a `{page}` parameter never resolves to a board, and a `{board}` parameter never resolves to a page.
     */
    protected function configureRouteBindings(): void
    {
        $router = $this->app['router'];

        $router->bind('page', fn (string $value) => Page::query()->where('type', PageType::Page)->findOrFail($value));
        $router->bind('board', fn (string $value) => Page::query()->where('type', PageType::Board)->findOrFail($value));
    }

    /**
     * The OpenAPI document covers /api/v1 and is readable by signed-in users only.
     */
    protected function configureApiDocs(): void
    {
        Gate::define('viewApiDocs', fn (?User $user) => $user !== null);

        Scramble::configure()
            ->routes(fn (Route $route) => Str::startsWith($route->uri(), 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->info->title = config('app.name').' API';
                $openApi->info->description = 'Authenticate with `Authorization: Bearer <token>`. Create a personal access token under Settings > API tokens.';
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }

    /**
     * Passport is the authorization server for the MCP endpoint (OAuth with dynamic client registration)
     * and issues personal access tokens for the REST API.
     */
    protected function configurePassport(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::authorizationView('mcp.authorize');
        Passport::tokensCan(ApiScopes::definitions());
        Passport::tokensExpireIn(now()->plus(hours: 1));
        Passport::refreshTokensExpireIn(now()->plus(days: 30));
    }

    /**
     * `toProps()` renders a resource or collection to a plain array for Inertia props, with the
     * same shape the API returns, nested resources and conditional fields resolved.
     */
    protected function configureResources(): void
    {
        JsonResource::macro('toProps', function (): array {
            /** @var JsonResource $this */
            return $this->response()->getData(true)['data'];
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $clientId = $request->user()?->token()?->oauth_client_id;

            return Limit::perMinute(600)->by(($request->user()?->id ?: $request->ip()).':'.($clientId ?? 'session'));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
