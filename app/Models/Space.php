<?php

namespace App\Models;

use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property SpaceVisibility $visibility
 * @property int|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'visibility'])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => SpaceVisibility::class,
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isPersonal(): bool
    {
        return $this->owner_id !== null;
    }

    /**
     * Why visibility cannot be changed, or null when it can.
     */
    public function visibilityLockedReason(): ?string
    {
        return match (true) {
            $this->isPersonal() => 'Personal spaces are always private.',
            default => null,
        };
    }

    /**
     * Why members cannot be managed, or null when they can.
     */
    public function membershipLockedReason(): ?string
    {
        return match (true) {
            $this->isPersonal() => 'Personal spaces belong only to you.',
            default => null,
        };
    }

    /**
     * Trashed pages count: deleting the space would destroy them too.
     */
    public function hasContent(): bool
    {
        return $this->pages()->withTrashed()->exists();
    }

    /**
     * Why the space cannot be deleted, or null when it can.
     */
    public function deletionLockedReason(): ?string
    {
        return match (true) {
            $this->hasContent() => 'Move or delete this space\'s content first.',
            default => null,
        };
    }

    public function isPrivate(): bool
    {
        return $this->visibility === SpaceVisibility::Private;
    }

    /**
     * Roles looked up on this instance, so listing many records that share a space costs one query per user.
     * Actions that change membership call forgetRoleCache().
     *
     * @var array<int, ?SpaceRole>
     */
    private array $roleCache = [];

    public function roleFor(User $user): ?SpaceRole
    {
        if (! array_key_exists($user->id, $this->roleCache)) {
            $role = $this->users()->whereKey($user->id)->first()?->pivot->role;

            $this->roleCache[$user->id] = $role === null ? null : SpaceRole::from($role);
        }

        return $this->roleCache[$user->id];
    }

    public function forgetRoleCache(): void
    {
        $this->roleCache = [];
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->is_admin || ! $this->isPrivate()) {
            return true;
        }

        return $this->roleFor($user) !== null;
    }

    public function isEditableBy(User $user): bool
    {
        if ($user->is_admin || $this->visibility === SpaceVisibility::Open) {
            return true;
        }

        return $this->roleFor($user)?->canEdit() ?? false;
    }

    public function isManagedBy(User $user): bool
    {
        return $user->is_admin || $this->roleFor($user) === SpaceRole::Manager;
    }

    public function managerCount(): int
    {
        return $this->users()->wherePivot('role', SpaceRole::Manager->value)->count();
    }

    /**
     * @param  Builder<Space>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', '!=', SpaceVisibility::Private->value)
                ->orWhereHas('users', fn (Builder $users) => $users->whereKey($user->id));
        });
    }

    /**
     * @param  Builder<Space>  $query
     */
    public function scopeEditableBy(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', SpaceVisibility::Open->value)
                ->orWhereHas('users', fn (Builder $users) => $users
                    ->whereKey($user->id)
                    ->whereIn('space_user.role', [SpaceRole::Manager->value, SpaceRole::Editor->value]));
        });
    }

    /**
     * What the user can do here, in the flat shape the frontend reads.
     *
     * @return array{role: string|null, can_edit: bool, can_manage: bool}
     */
    public function abilitiesFor(User $user): array
    {
        return [
            'role' => $this->roleFor($user)?->value,
            'can_edit' => $this->isEditableBy($user),
            'can_manage' => $user->can('manageMembers', $this),
        ];
    }
}
