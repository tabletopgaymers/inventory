<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    protected $guarded = ['id'];

    protected $attributes = ['first_name' => '', 'last_name' => '', 'enabled' => true, 'contact_attested' => false];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'contact_attested' => 'boolean'];
    }

    public function roles(): array
    {
        return DB::table('user_roles')->where('user_id', $this->id)->orderBy('role')->pluck('role')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    public function validContact(): bool
    {
        return $this->contact_attested && is_string($this->contact_email)
            && filter_var($this->contact_email, FILTER_VALIDATE_EMAIL)
            && strtolower(substr(strrchr($this->contact_email, '@'), 1)) === 'tabletopgaymers.org';
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name) ?: 'Account '.$this->id;
    }
}
