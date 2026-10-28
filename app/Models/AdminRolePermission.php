<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One permission of a role: "page.action" from App\Support\AdminPages */
class AdminRolePermission extends Model
{
    protected $fillable = ['permission'];

    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }
}
