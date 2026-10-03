<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['slug', 'name', 'capabilities'];

    protected function casts(): array
    {
        return ['capabilities' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }

    public function isCore(): bool
    {
        return array_key_exists($this->slug, \App\Cms\Support\Capabilities::DEFAULT_ROLES);
    }
}
