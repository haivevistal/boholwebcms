/**
 * Simple Shop admin script — registers a React screen without any build
 * step by using the React instance and UI kit exposed on window.CMS.
 * The PHP side returns ['component' => 'simple-shop/Reports'] from its
 * add_submenu_page() callback.
 */
(function () {
    const CMS = window.CMS;
    if (!CMS || CMS.context !== 'admin') return;
    const { React } = CMS;
    const { useEffect, useState } = React;
    const h = React.createElement;
    const { Card, Button, Select, Spinner, PageHeader } = CMS.components;

    function Reports({ ordersUrl }) {
        const [days, setDays] = useState('14');
        const [data, setData] = useState(null);

        useEffect(() => {
            setData(null);
            CMS.adminAjax('shop_report', { days }).then((res) => setData(res.data));
        }, [days]);

        const max = data ? Math.max(1, ...data.series.map((d) => d.total)) : 1;
        const total = data ? data.series.reduce((s, d) => s + d.total, 0) : 0;
        const orders = data ? data.series.reduce((s, d) => s + d.orders, 0) : 0;

        return h(
            'div',
            null,
            h(PageHeader, {
                title: 'Sales Reports',
                description: 'A React screen registered by a plugin with CMS.registerAdminComponent(), fed by an admin AJAX action.',
                actions: h(Select, { value: days, onChange: (e) => setDays(e.target.value), options: { 7: 'Last 7 days', 14: 'Last 14 days', 30: 'Last 30 days', 90: 'Last 90 days' }, style: { width: 160 } }),
            }),
            h(
                'div',
                { style: { display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', marginBottom: 24 } },
                [['Revenue', data ? `${data.symbol}${total.toFixed(2)}` : '…'], ['Orders', data ? orders : '…'], ['Average order', data && orders ? `${data.symbol}${(total / orders).toFixed(2)}` : '—']].map(([label, value]) =>
                    h(Card, { key: label }, h('p', { style: { fontSize: 13, color: '#64748b' } }, label), h('p', { style: { fontSize: 24, fontWeight: 600, marginTop: 4 } }, value)),
                ),
            ),
            h(
                Card,
                { title: 'Revenue per day', actions: h(Button, { href: ordersUrl, variant: 'secondary', size: 'sm' }, 'View orders') },
                !data
                    ? h('div', { style: { display: 'flex', justifyContent: 'center', padding: 48 } }, h(Spinner))
                    : h(
                          'div',
                          { style: { display: 'flex', alignItems: 'flex-end', gap: 4, height: 224, paddingTop: 16 } },
                          data.series.map((d) =>
                              h(
                                  'div',
                                  { key: d.date, style: { flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'flex-end', gap: 4, height: '100%' }, title: `${d.date}: ${data.symbol}${d.total} (${d.orders} orders)` },
                                  h('div', { style: { width: '100%', borderRadius: '4px 4px 0 0', background: '#6366f1', height: `${Math.max(2, (d.total / max) * 100)}%` } }),
                                  h('span', { style: { fontSize: 10, color: '#94a3b8' } }, d.date.slice(5)),
                              ),
                          ),
                      ),
            ),
        );
    }

    CMS.registerAdminComponent('simple-shop/Reports', Reports);

    // Add a panel to the post editor sidebar for products only (JS filter).
    CMS.hooks.addFilter('admin.editor.sidebar', 'simple-shop', (panels, { postType, post }) => {
        if (postType.name !== 'product' || !post.id) return panels;
        return [
            ...panels,
            h(Card, { key: 'shop-tip', title: 'Shop tip' }, h('p', { style: { fontSize: 14, color: '#475569' } }, 'Embed this product anywhere with ', h('code', null, `[add_to_cart id="${post.id}"]`))),
        ];
    });
})();
