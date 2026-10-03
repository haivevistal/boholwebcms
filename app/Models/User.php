<?php

namespace App\Models;

use App\Models\Concerns\HasMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasMeta, Notifiable;

    protected $fillable = [
        'username', 'name', 'first_name', 'last_name', 'email', 'password', 'role', 'website', 'bio',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function meta(): HasMany
    {
        return $this->hasMany(UserMeta::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function can($abilities, $arguments = []): bool
    {
        // Route CMS capabilities through the CMS capability system so
        // @can / $user->can() / Gate checks all behave the same way.
        if (is_string($abilities)) {
            return user_can($this, $abilities, ...(array) $arguments);
        }

        return parent::can($abilities, $arguments);
    }

    public function avatarUrl(int $size = 64): string
    {
        $hash = md5(strtolower(trim((string) $this->email)));

        return apply_filters('get_avatar_url', "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp", $this, $size);
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatarUrl(),
            'url' => app(\App\Cms\Support\Permalinks::class)->author($this),
            'bio' => $this->bio,
        ];
    }
}
