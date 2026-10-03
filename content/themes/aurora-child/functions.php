<?php
/**
 * Child theme functions run BEFORE the parent theme's functions.php.
 */

defined('CMS_LOADED') || exit;

// Add something to the parent's footer region.
add_action('cms_region_footer', function () {
    echo '<section><h3>About</h3><p>This footer column comes from the <strong>Aurora Child</strong> theme’s functions.php.</p></section>';
});
