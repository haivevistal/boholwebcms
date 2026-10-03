<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Key/value meta storage (post meta, user meta). Scalars are stored as
 * strings; arrays/objects as JSON and decoded transparently.
 */
trait HasMeta
{
    /** @var array<string, mixed>|null */
    protected ?array $metaCache = null;

    abstract public function meta(): HasMany;

    public function getMeta(string $key, mixed $default = null): mixed
    {
        $this->loadMetaCache();

        return array_key_exists($key, $this->metaCache) ? $this->metaCache[$key] : $default;
    }

    public function allMeta(): array
    {
        $this->loadMetaCache();

        return $this->metaCache;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $this->meta()->updateOrCreate(['meta_key' => $key], ['meta_value' => static::encodeMeta($value)]);
        $this->loadMetaCache();
        $this->metaCache[$key] = static::decodeMeta(static::encodeMeta($value));
    }

    public function deleteMeta(string $key): void
    {
        $this->meta()->where('meta_key', $key)->delete();
        $this->loadMetaCache();
        unset($this->metaCache[$key]);
    }

    public function flushMetaCache(): void
    {
        $this->metaCache = null;
        $this->unsetRelation('meta');
    }

    protected function loadMetaCache(): void
    {
        if ($this->metaCache !== null) {
            return;
        }

        $this->metaCache = [];
        if (! $this->exists) {
            return;
        }

        foreach ($this->meta as $row) {
            $this->metaCache[$row->meta_key] = static::decodeMeta($row->meta_value);
        }
    }

    public static function encodeMeta(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    public static function decodeMeta(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $first = $value[0] ?? '';
        if ($first === '{' || $first === '[') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}
