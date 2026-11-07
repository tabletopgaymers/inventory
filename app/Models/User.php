<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    public const DISPLAY_DENSITIES = ['compact' => 'Compact', 'standard' => 'Standard', 'spacious' => 'Spacious'];

    protected $guarded = ['id'];

    protected $attributes = ['first_name' => '', 'last_name' => '', 'enabled' => true];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function roles(): array
    {
        return DB::table('user_roles')->where('user_id', $this->id)->orderBy('role')->pluck('role')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    public function canAdjustUnitCost(): bool
    {
        return $this->enabled && array_intersect($this->roles(), ['admin', 'procurement']) !== [];
    }

    public function deliveryEmail(): string
    {
        foreach ([$this->personal_email, $this->provider_email] as $email) {
            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
                && ! str_contains(strtoupper($email), '#EXT#')) {
                return $email;
            }
        }

        return '';
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name) ?: 'Account '.$this->id;
    }
}
