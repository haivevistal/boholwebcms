<?php

namespace App\Cms\Hooks;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * WordPress-style actions & filters.
 *
 *   add_filter('the_title', fn ($title, $post) => strtoupper($title), 10, 2);
 *   add_action('save_post', [$this, 'onSave'], 20);
 *   apply_filters('the_title', $title, $post);
 *   do_action('save_post', $post);
 *
 * Actions are filters that don't return a value, exactly like WordPress.
 * Lower priority runs first; equal priorities run in the order they were added.
 */
class HookManager
{
    /** @var array<string, array<int, array<string, array{callback: callable, accepted_args: int}>>> */
    protected array $hooks = [];

    /** @var array<string, bool> hooks whose priority buckets need sorting */
    protected array $dirty = [];

    /** @var array<string, int> number of times each action has fired */
    protected array $actionCounts = [];

    /** @var array<int, string> stack of hooks currently running */
    protected array $current = [];

    /* ---------------------------------------------------------------------
     | Registration
     * ------------------------------------------------------------------- */

    /**
     * @param  int|null  $acceptedArgs  null = detect from the callback signature
     */
    public function addFilter(string $hook, callable|array|string $callback, int $priority = 10, ?int $acceptedArgs = null): bool
    {
        $id = $this->callbackId($callback);

        $this->hooks[$hook][$priority][$id] = [
            'callback' => $callback,
            'accepted_args' => $acceptedArgs ?? $this->detectArgCount($callback),
        ];
        $this->dirty[$hook] = true;

        return true;
    }

    public function addAction(string $hook, callable|array|string $callback, int $priority = 10, ?int $acceptedArgs = null): bool
    {
        return $this->addFilter($hook, $callback, $priority, $acceptedArgs);
    }

    public function removeFilter(string $hook, callable|array|string $callback, ?int $priority = null): bool
    {
        $id = $this->callbackId($callback);
        $removed = false;

        foreach ($this->hooks[$hook] ?? [] as $p => $callbacks) {
            if ($priority !== null && $p !== $priority) {
                continue;
            }
            if (isset($callbacks[$id])) {
                unset($this->hooks[$hook][$p][$id]);
                $removed = true;
                if (empty($this->hooks[$hook][$p])) {
                    unset($this->hooks[$hook][$p]);
                }
            }
        }

        if (empty($this->hooks[$hook])) {
            unset($this->hooks[$hook]);
        }

        return $removed;
    }

    public function removeAction(string $hook, callable|array|string $callback, ?int $priority = null): bool
    {
        return $this->removeFilter($hook, $callback, $priority);
    }

    public function removeAll(string $hook, ?int $priority = null): void
    {
        if ($priority === null) {
            unset($this->hooks[$hook]);

            return;
        }
        unset($this->hooks[$hook][$priority]);
    }

    /**
     * Without a callback: whether anything is attached.
     * With a callback: the priority it's attached at, or false.
     */
    public function hasFilter(string $hook, callable|array|string|null $callback = null): bool|int
    {
        if ($callback === null) {
            return ! empty($this->hooks[$hook]);
        }

        $id = $this->callbackId($callback);
        foreach ($this->hooks[$hook] ?? [] as $priority => $callbacks) {
            if (isset($callbacks[$id])) {
                return $priority;
            }
        }

        return false;
    }

    public function hasAction(string $hook, callable|array|string|null $callback = null): bool|int
    {
        return $this->hasFilter($hook, $callback);
    }

    /* ---------------------------------------------------------------------
     | Execution
     * ------------------------------------------------------------------- */

    public function applyFilters(string $hook, mixed $value = null, mixed ...$args): mixed
    {
        // The special "all" hook sees every filter & action (debugging tools).
        if ($hook !== 'all' && ! empty($this->hooks['all'])) {
            $this->runAll($hook, [$value, ...$args]);
        }

        if (empty($this->hooks[$hook])) {
            return $value;
        }

        $this->current[] = $hook;

        try {
            foreach ($this->sorted($hook) as $callbacks) {
                foreach ($callbacks as $entry) {
                    $all = [$value, ...$args];
                    $params = $entry['accepted_args'] < 0
                        ? $all
                        : array_slice($all, 0, max(1, $entry['accepted_args']));
                    $value = call_user_func_array($entry['callback'], $params);
                }
            }
        } finally {
            array_pop($this->current);
        }

        return $value;
    }

    /**
     * Same as applyFilters() but the arguments are passed as an array.
     */
    public function applyFiltersRefArray(string $hook, array $args): mixed
    {
        return $this->applyFilters($hook, ...$args);
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        $this->actionCounts[$hook] = ($this->actionCounts[$hook] ?? 0) + 1;

        if ($hook !== 'all' && ! empty($this->hooks['all'])) {
            $this->runAll($hook, $args);
        }

        if (empty($this->hooks[$hook])) {
            return;
        }

        $this->current[] = $hook;

        try {
            foreach ($this->sorted($hook) as $callbacks) {
                foreach ($callbacks as $entry) {
                    $params = $entry['accepted_args'] < 0
                        ? $args
                        : array_slice($args, 0, $entry['accepted_args']);
                    call_user_func_array($entry['callback'], $params);
                }
            }
        } finally {
            array_pop($this->current);
        }
    }

    public function didAction(string $hook): int
    {
        return $this->actionCounts[$hook] ?? 0;
    }

    public function currentFilter(): ?string
    {
        return $this->current ? end($this->current) : null;
    }

    public function doingFilter(?string $hook = null): bool
    {
        return $hook === null ? ! empty($this->current) : in_array($hook, $this->current, true);
    }

    /**
     * Debug helper: list of hooks with the number of callbacks attached.
     *
     * @return array<string, int>
     */
    public function registered(): array
    {
        $out = [];
        foreach ($this->hooks as $hook => $priorities) {
            $out[$hook] = array_sum(array_map('count', $priorities));
        }
        ksort($out);

        return $out;
    }

    /* ---------------------------------------------------------------------
     | Internals
     * ------------------------------------------------------------------- */

    protected function sorted(string $hook): array
    {
        if (! empty($this->dirty[$hook])) {
            ksort($this->hooks[$hook], SORT_NUMERIC);
            unset($this->dirty[$hook]);
        }

        // Copy so callbacks may add/remove hooks while we iterate.
        return $this->hooks[$hook];
    }

    protected function runAll(string $hook, array $args): void
    {
        foreach ($this->sorted('all') as $callbacks) {
            foreach ($callbacks as $entry) {
                call_user_func($entry['callback'], $hook, ...$args);
            }
        }
    }

    protected function callbackId(callable|array|string $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if ($callback instanceof Closure || is_object($callback)) {
            return spl_object_hash($callback);
        }

        if (is_array($callback)) {
            [$target, $method] = $callback + [null, null];
            $prefix = is_object($target) ? spl_object_hash($target) : (string) $target;

            return $prefix.'::'.$method;
        }

        return md5(serialize($callback));
    }

    /**
     * Detect how many arguments a callback accepts so we never pass extra
     * arguments to internal PHP functions (e.g. "trim"), which would throw.
     * Returns -1 for variadic callbacks (= pass everything).
     */
    protected function detectArgCount(callable|array|string $callback): int
    {
        try {
            if (is_array($callback)) {
                $ref = new ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && str_contains($callback, '::')) {
                $ref = new ReflectionMethod($callback);
            } elseif (is_object($callback) && ! $callback instanceof Closure) {
                $ref = new ReflectionMethod($callback, '__invoke');
            } else {
                $ref = new ReflectionFunction(Closure::fromCallable($callback));
            }

            // Internal functions (trim, strtoupper, ...) only get their
            // required arguments, so e.g. trim() never receives a charlist.
            if ($ref->isInternal()) {
                return max(1, $ref->getNumberOfRequiredParameters());
            }

            if ($ref->isVariadic()) {
                return -1;
            }

            return $ref->getNumberOfParameters();
        } catch (Throwable) {
            return 1;
        }
    }
}
