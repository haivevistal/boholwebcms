import { useSyncExternalStore } from 'react';

/**
 * Tiny observable key/value registry. Theme bundles and plugin scripts may
 * load after React has rendered, so components subscribe and re-render
 * as soon as the thing they need is registered.
 */
export function createRegistry() {
    const items = {};
    const listeners = new Set();
    let version = 0;

    return {
        items,
        set(key, value) {
            items[key] = value;
            version++;
            listeners.forEach((l) => l());
        },
        get: (key) => items[key],
        has: (key) => key in items,
        subscribe(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        version: () => version,
    };
}

export function useRegistry(registry) {
    useSyncExternalStore(registry.subscribe, registry.version, registry.version);
    return registry.items;
}

/** Resolve once a key has been registered. */
export function waitFor(registry, key, timeout = 10000) {
    if (registry.has(key)) return Promise.resolve(registry.get(key));
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            unsubscribe();
            reject(new Error(`"${key}" was not registered within ${timeout}ms`));
        }, timeout);
        const unsubscribe = registry.subscribe(() => {
            if (registry.has(key)) {
                clearTimeout(timer);
                unsubscribe();
                resolve(registry.get(key));
            }
        });
    });
}
