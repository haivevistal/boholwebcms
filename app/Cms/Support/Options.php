<?php

namespace App\Cms\Support;

use App\Models\Option;
use Throwable;

/**
 * Key/value site options (like wp_options). Values are JSON encoded so
 * arrays, booleans and numbers keep their type.
 *
 * Hooks:  pre_option_{name}, default_option_{name}, option_{name},
 *         pre_update_option_{name}, update_option, updated_option, update_option_{name},
 *         added_option, delete_option, deleted_option
 */
class Options
{
    /** @var array<string, mixed>|null */
    protected ?array $cache = null;

    /** @var array<string, true> */
    protected array $missing = [];

    public function get(string $name, mixed $default = null): mixed
    {
        $pre = apply_filters("pre_option_{$name}", false, $name, $default);
        if ($pre !== false) {
            return $pre;
        }

        $this->load();

        if (array_key_exists($name, $this->cache)) {
            $value = $this->cache[$name];
        } elseif (isset($this->missing[$name])) {
            return apply_filters("default_option_{$name}", $default, $name);
        } else {
            // Not autoloaded — fetch on demand.
            $row = $this->query(fn () => Option::where('name', $name)->first());
            if (! $row) {
                $this->missing[$name] = true;

                return apply_filters("default_option_{$name}", $default, $name);
            }
            $value = $this->decode($row->value);
            $this->cache[$name] = $value;
        }

        return apply_filters("option_{$name}", $value, $name);
    }

    public function update(string $name, mixed $value, ?bool $autoload = null): bool
    {
        $this->load();
        $old = $this->get($name);

        $value = apply_filters("pre_update_option_{$name}", $value, $old, $name);
        $value = apply_filters('pre_update_option', $value, $name, $old);

        if ($value === $old && array_key_exists($name, $this->cache)) {
            return false;
        }

        $exists = array_key_exists($name, $this->cache) || Option::where('name', $name)->exists();

        do_action('update_option', $name, $old, $value);

        $attrs = ['value' => $this->encode($value)];
        if ($autoload !== null || ! $exists) {
            $attrs['autoload'] = $autoload ?? true;
        }

        Option::updateOrCreate(['name' => $name], $attrs);

        $this->cache[$name] = $value;
        unset($this->missing[$name]);

        if ($exists) {
            do_action("update_option_{$name}", $old, $value, $name);
            do_action('updated_option', $name, $old, $value);
        } else {
            do_action("add_option_{$name}", $name, $value);
            do_action('added_option', $name, $value);
        }

        return true;
    }

    public function add(string $name, mixed $value, bool $autoload = true): bool
    {
        $this->load();
        if (array_key_exists($name, $this->cache) || Option::where('name', $name)->exists()) {
            return false;
        }

        return $this->update($name, $value, $autoload);
    }

    public function delete(string $name): bool
    {
        do_action('delete_option', $name);
        $deleted = Option::where('name', $name)->delete() > 0;
        unset($this->cache[$name]);
        $this->missing[$name] = true;

        if ($deleted) {
            do_action("delete_option_{$name}", $name);
            do_action('deleted_option', $name);
        }

        return $deleted;
    }

    public function flush(): void
    {
        $this->cache = null;
        $this->missing = [];
    }

    protected function load(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];
        $rows = $this->query(fn () => Option::where('autoload', true)->get(['name', 'value'])) ?? [];
        foreach ($rows as $row) {
            $this->cache[$row->name] = $this->decode($row->value);
        }
    }

    protected function query(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null; // not installed yet
        }
    }

    protected function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function decode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
