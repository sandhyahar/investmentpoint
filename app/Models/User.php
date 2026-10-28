<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable. Never role / admin_role_id / is_active /
     * must_change_password: those are set only by the Super Admin's own code (forceFill / forceCreate),
     * so no form can give itself admin-panel rights.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    /** Permission keys, worked out once per request */
    private ?array $permissionCache = null;

    public function blogs()
    {
        return $this->hasMany(UserBlog::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    /** The Super Admin: every page and action of the admin panel, and User & Role / Role & Permission Management */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** An admin-panel user with a role made by the Super Admin: only that role's permissions */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** An admin-panel login (the Super Admin, or a staff user), active or not */
    public function canUseAdminPanel(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function adminRole()
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    /** May sign in now: an active account, and for a staff user an active role */
    public function canSignInToAdmin(): bool
    {
        if (!$this->canUseAdminPanel() || $this->is_active === false) {
            return false;
        }

        return $this->isAdmin() || (bool) $this->adminRole?->is_active;
    }

    /** Permission keys ("news.create") of this user: all for the Super Admin, the role's for staff (none when inactive) */
    public function adminPermissions(): array
    {
        if ($this->isAdmin()) {
            return \App\Support\AdminPages::allPermissions();
        }

        return $this->permissionCache ??= $this->isStaff() && $this->canSignInToAdmin()
            ? $this->adminRole->permissionKeys() : [];
    }

    /** Pages this user may at least view */
    public function adminPages(): array
    {
        return array_values(array_unique(array_map(fn ($p) => strstr($p, '.', true), $this->adminPermissions())));
    }

    /** Can open this admin route (by name), e.g. to show its link in the sidebar */
    public function canOpenAdminRoute(string $route): bool
    {
        return \App\Support\AdminPages::allows($this, $route);
    }

    /** The paid subscription that is running now (latest expiry), or null. */
    public function activeSubscription(): ?SubscriptionRequest
    {
        // running(): real payments only (demo / test payments count only while testing)
        return $this->subscriptions()->running()->withMemberAccess()->where('starts_at', '<=', now())
            ->orderByDesc('expires_at')->first();
    }

    /** Last day of access, counting renewals paid in advance. */
    public function subscriptionEndsAt(): ?\Illuminate\Support\Carbon
    {
        $end = $this->subscriptions()->running()->withMemberAccess()->max('expires_at');

        return $end ? \Illuminate\Support\Carbon::parse($end) : null;
    }
}
