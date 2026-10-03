/**
 * JavaScript actions & filters — the client-side twin of the PHP hook API.
 *
 *   CMS.hooks.addFilter('admin.post.sidebar', 'my-plugin', (panels, ctx) => [...panels, MyPanel]);
 *   CMS.hooks.addAction('theme.mounted', 'my-plugin', (props) => console.log(props.view.kind));
 *   const value = CMS.hooks.applyFilters('hook.name', value, ...args);
 */
export function createHooks() {
    const store = { filters: {}, actions: {} };
    const counts = {};

    const add = (type) => (hook, namespace, callback, priority = 10) => {
        if (typeof namespace === 'function') {
            // addFilter(hook, callback, priority?) shorthand
            priority = typeof callback === 'number' ? callback : 10;
            callback = namespace;
            namespace = 'anonymous';
        }
        const list = (store[type][hook] ||= []);
        list.push({ namespace, callback, priority, seq: list.length });
        list.sort((a, b) => a.priority - b.priority || a.seq - b.seq);
    };

    const remove = (type) => (hook, namespace) => {
        if (!store[type][hook]) return 0;
        const before = store[type][hook].length;
        store[type][hook] = store[type][hook].filter((h) => h.namespace !== namespace);
        return before - store[type][hook].length;
    };

    return {
        addFilter: add('filters'),
        addAction: add('actions'),
        removeFilter: remove('filters'),
        removeAction: remove('actions'),
        hasFilter: (hook, ns) => !!store.filters[hook]?.some((h) => !ns || h.namespace === ns),
        hasAction: (hook, ns) => !!store.actions[hook]?.some((h) => !ns || h.namespace === ns),
        applyFilters(hook, value, ...args) {
            for (const h of store.filters[hook] || []) {
                try {
                    value = h.callback(value, ...args);
                } catch (e) {
                    console.error(`[CMS] filter "${hook}" (${h.namespace}) failed`, e);
                }
            }
            return value;
        },
        doAction(hook, ...args) {
            counts[hook] = (counts[hook] || 0) + 1;
            for (const h of store.actions[hook] || []) {
                try {
                    h.callback(...args);
                } catch (e) {
                    console.error(`[CMS] action "${hook}" (${h.namespace}) failed`, e);
                }
            }
        },
        didAction: (hook) => counts[hook] || 0,
    };
}
