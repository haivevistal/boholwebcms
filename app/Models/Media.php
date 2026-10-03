<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'user_id', 'title', 'alt', 'caption', 'description', 'filename', 'path', 'disk', 'mime_type', 'size', 'width', 'height', 'sizes',
    ];

    protected $appends = ['url', 'is_image'];

    protected function casts(): array
    {
        return ['sizes' => 'array', 'size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return apply_filters('media_url', Storage::disk($this->disk)->url($this->path), $this);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function sizeUrl(string $size): string
    {
        $path = $this->sizes[$size]['path'] ?? null;

        return $path ? Storage::disk($this->disk)->url($path) : $this->url;
    }

    public function sizeUrls(): array
    {
        $out = ['full' => $this->url];
        foreach ((array) $this->sizes as $name => $info) {
            $out[$name] = Storage::disk($this->disk)->url($info['path']);
        }

        return $out;
    }

    public function toFrontArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'alt' => $this->alt ?: $this->title,
            'caption' => $this->caption,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mime_type,
            'sizes' => $this->sizeUrls(),
        ];
    }
}
