<?php

namespace App\Modules\Core\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SuperAdmin extends Authenticatable
{
    // Guard séparé — hors système tenant, jamais mélangé avec User.
    // Accès exclusif depuis des IPs whitelistées (voir config/auth.php).

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];
}
