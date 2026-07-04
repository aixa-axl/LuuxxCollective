<?php
/**
 * Required fallback template. All real pages route through page.php.
 */
get_header();

while (have_posts()) : the_post();
    luux_render_sections();
endwhile;

get_footer();
