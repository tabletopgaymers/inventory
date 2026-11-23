<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SavedList extends Model
{
    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    protected $fillable = ['name', 'description', 'storage_location_id', 'status'];

    protected $attributes = ['status' => 'active', 'shared' => false];

    protected $casts = ['shared' => 'boolean'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function itemIds(): array
    {
        return DB::table('saved_list_items')->where('saved_list_id', $this->id)
            ->orderBy('item_id')->pluck('item_id')->map(fn ($id) => (int) $id)->all();
    }

    public function canEdit(User $user): bool
    {
        return $user->enabled && ((int) $this->owner_id === (int) $user->id || $user->hasRole('admin'));
    }

    public function canView(User $user): bool
    {
        return $user->enabled && ($this->shared || $this->canEdit($user));
    }
}
