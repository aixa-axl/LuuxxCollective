<?php
/**
 * Default page template.
 * Every page (home, about, hotel, destination, contact, landings)
 * is assembled from page_sections flexible content — one template for all.
 */

get_header();

while (have_posts()) : the_post();
    luux_render_sections();
endwhile;

get_footer();
