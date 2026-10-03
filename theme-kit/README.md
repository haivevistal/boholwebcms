# BoholwebCMS Theme Kit

Themes are React. A theme is a folder in `content/themes/{slug}`:

```
content/themes/my-theme/
├── theme.json         ← manifest (name, regions, menus, page templates, customizer)
├── functions.php      ← optional, loaded on every request while active (hooks, shortcodes…)
├── screenshot.png     ← shown in Appearance → Themes
├── src/index.jsx      ← React source (export default { Layout, templates })
└── dist/theme.js      ← built bundle (+ dist/theme.css) — this is what the site loads
```

Build with `npm run build:themes` (all) or `npm run build:themes -- my-theme`.
Add `--watch` while developing: `node theme-kit/build-all.mjs my-theme --watch`.

## Entry point

```jsx
import { Content, Menu, Region, Comments, Pagination, useTheme, useThemeMod } from '@boholwebcms/theme';
import { Link } from '@inertiajs/react';
import './style.css';

function Layout({ site, children }) {
    return (<><header><Link href={site.url}>{site.name}</Link><Menu location="primary" /></header>
             <main>{children}</main>
             <footer><Region name="footer" /></footer></>);
}

const Single = ({ view }) => (<article><h1>{view.post.title}</h1><Content html={view.post.content} /><Comments /></article>);
const Index  = ({ view }) => view.posts.map((p) => <h2 key={p.id}><Link href={p.permalink}>{p.title}</Link></h2>);

export default { Layout, templates: { index: Index, single: Single, page: Single } };
```

The server sends a **template hierarchy** in `view.templates`, most specific first — exactly like
WordPress — and the first template your theme (or a plugin) implements is used:

| View | Hierarchy |
|---|---|
| Static front page | `front-page`, `template-{name}`, `page-{slug}`, `page-{id}`, `page`, `singular`, `index` |
| Blog home | `front-page`, `home`, `index` |
| Single post / CPT | `template-{name}`, `single-{type}-{slug}`, `single-{type}`, `single`, `singular`, `index` |
| Page | `template-{name}`, `page-{slug}`, `page-{id}`, `page`, `singular`, `index` |
| Category / Tag | `category-{slug}`, `category`, `archive`, `index` (tag: `tag-…`) |
| Custom taxonomy | `taxonomy-{tax}-{term}`, `taxonomy-{tax}`, `taxonomy`, `archive`, `index` |
| Post type archive | `archive-{type}`, `archive`, `index` |
| Author / Date | `author-{username}`, `author`, `archive`, `index` / `date`, `archive`, `index` |
| Search / 404 | `search`, `index` / `404`, `index` |

`template-{name}` comes from the page template chosen in the editor (`page_templates` in theme.json).

## Props every template receives

`site` · `theme` (`slug`, `mods` = Customizer values, `media` = URLs for image controls) · `view`
(`kind`, `templates`, `title`, `post`, `posts`, `pagination`, `archive`, `search_query`) · `menus` ·
`regions` · `comments` · `adminBar` · `privacy` · `meta`.

## Helpers from `@boholwebcms/theme`

`useTheme()`, `useThemeMod(name, fallback)`, `useThemeMedia(id)`, `Content`, `Menu`, `Region`, `Slot`,
`Pagination`, `SearchForm`, `Comments`, `CommentForm`, `PasswordForm`, `SmartLink`, `formatDate`,
`applyFilters`, `doAction`, plus `Head`, `Link`, `router`, `useForm`, `usePage` from Inertia.

## Child themes

Set `"parent": "aurora"` in theme.json. The parent bundle loads first; the child bundle's templates
override the parent's by name, and its `functions.php` runs before the parent's.
