<?php

namespace App\Models;

use App\Support\AdminPages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** A role for admin-panel users: name, description, Active switch, and its permissions ("news.create" ...) */
class AdminRole extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }

    public function permissions()
    {
        return $this->hasMany(AdminRolePermission::class);
    }

    /** Permission keys of this role (only ones that still exist), in the table's order */
    public function permissionKeys(): array
    {
        return AdminPages::clean($this->permissions->pluck('permission')->all());
    }

    /** Save exactly these permissions (unknown keys are dropped; View is added where needed) */
    public function syncPermissions(array $keys): void
    {
        $keys = AdminPages::clean($keys);
        DB::transaction(function () use ($keys) {
            $this->permissions()->whereNotIn('permission', $keys)->delete();
            $have = $this->permissions()->pluck('permission')->all();
            foreach (array_diff($keys, $have) as $key) {
                $this->permissions()->create(['permission' => $key]);
            }
        });
        $this->unsetRelation('permissions');
    }
}
