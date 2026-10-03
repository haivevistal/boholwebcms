import './style.css';
import Layout from './Layout';
import Index from './templates/Index';
import FrontPage from './templates/FrontPage';
import Single from './templates/Single';
import Page from './templates/Page';
import Archive from './templates/Archive';
import Search from './templates/Search';
import NotFound from './templates/NotFound';

/**
 * Aurora — template map. Keys follow the WordPress template hierarchy.
 * Page templates chosen in the editor arrive as "template-{name}".
 */
export default {
    Layout,
    templates: {
        index: Index,
        home: Index,
        'front-page': FrontPage,
        single: Single,
        page: Page,
        'template-full-width': (props) => <Page {...props} fullWidth />,
        'template-landing': (props) => <Page {...props} fullWidth landing />,
        archive: Archive,
        search: Search,
        404: NotFound,
    },
};
