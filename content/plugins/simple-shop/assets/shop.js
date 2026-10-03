/**
 * Simple Shop front-end script. Loaded after the CMS runtime, so window.CMS
 * is available. Shows two integration styles:
 *  - AJAX "add to cart" via CMS.ajax() -> PHP add_action('ajax_nopriv_shop_add_to_cart')
 *  - re-binding after Inertia navigations via the "cms:content" DOM event
 */
(function () {
    const CMS = window.CMS;
    if (!CMS) return;

    function toast(html) {
        const el = document.createElement('div');
        el.className = 'shop-toast';
        el.innerHTML = html;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    // Capture phase so we run before the CMS content form interceptor.
    document.addEventListener(
        'submit',
        async (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-shop-ajax')) return;
            e.preventDefault();
            e.stopPropagation();
            const data = Object.fromEntries(new FormData(form).entries());
            const button = form.querySelector('button');
            button && (button.disabled = true);
            try {
                const res = await CMS.ajax('shop_add_to_cart', { product_id: data.product_id, qty: data.qty || 1 });
                const count = res.data.count;
                document.querySelectorAll('[data-shop-cart-count]').forEach((n) => (n.textContent = count));
                // Refresh props so the "Cart (n)" menu item updates without a reload.
                CMS.Inertia.router.reload({ only: ['menus'], preserveScroll: true });
                toast(`${res.data.message} <a href="${window.SimpleShop?.cartUrl || '/cart'}">View cart →</a>`);
                CMS.hooks.doAction('shop.added_to_cart', res.data);
            } catch (err) {
                toast(err.response?.data?.data || 'Could not add to cart.');
            } finally {
                button && (button.disabled = false);
            }
        },
        true,
    );

    // Example JS filter: themes rendering <Slot name="after_post_content"> get a note on products.
    CMS.hooks.addFilter('theme.slot.after_post_content', 'simple-shop', (nodes, props) => {
        if (props.post?.type !== 'product') return nodes;
        return [...nodes, CMS.React.createElement('p', { key: 'shop-note', style: { opacity: 0.7, fontSize: 14 } }, '🚚 Free shipping on orders over $50.')];
    });
})();
