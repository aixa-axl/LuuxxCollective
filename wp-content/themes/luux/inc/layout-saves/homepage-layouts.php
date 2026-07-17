<?php
/**
 * Homepage flexible-content layouts — legacy save registrations.
 */

defined('ABSPATH') || exit;

/**
 * @param array<int, array<string, mixed>> $fields
 * @param array<int, array<string, mixed>> $repeaters
 * @param list<string>|null                $layout_keys
 */
function luux_layout_save_register_homepage(
    string $slug,
    array $fields,
    array $repeaters = [],
    ?array $layout_keys = null
): void {
    luux_layout_save_register([
        'slug'        => $slug,
        'layout_keys' => $layout_keys ?? [$slug, 'layout_luux_' . $slug],
        'stash_meta'  => '_luux_layout_stash_' . $slug,
        'fields'      => $fields,
        'repeaters'   => $repeaters,
    ]);
}

luux_layout_save_register_homepage('hero', [
    ['key' => 'field_luux_hero_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_hero_subheading', 'name' => 'subheading', 'type' => 'scalar'],
    ['key' => 'field_luux_hero_media_type', 'name' => 'media_type', 'type' => 'scalar'],
    ['key' => 'field_luux_hero_background_image', 'name' => 'background_image', 'type' => 'image'],
    ['key' => 'field_luux_hero_background_video', 'name' => 'background_video', 'type' => 'image'],
    ['key' => 'field_luux_hero_show_group_tag', 'name' => 'show_group_tag', 'type' => 'true_false'],
    ['key' => 'field_luux_hero_group_tag_logo', 'name' => 'group_tag_logo', 'type' => 'image'],
], [
    [
        'name'       => 'ctas',
        'key'        => 'field_luux_hero_ctas',
        'sub_fields' => [
            ['key' => 'field_luux_hero_cta_link', 'name' => 'link', 'type' => 'link'],
        ],
    ],
]);

luux_layout_save_register_homepage('cta_strip', [
    ['key' => 'field_luux_cta_strip_text', 'name' => 'text', 'type' => 'scalar'],
    ['key' => 'field_luux_cta_strip_primary_link', 'name' => 'primary_link', 'type' => 'link'],
    ['key' => 'field_luux_cta_strip_secondary_link', 'name' => 'secondary_link', 'type' => 'link'],
]);

luux_layout_save_register_homepage('contact_strip', [
    ['key' => 'field_luux_contact_strip_caption', 'name' => 'caption', 'type' => 'scalar'],
    ['key' => 'field_luux_contact_strip_text', 'name' => 'text', 'type' => 'scalar'],
    ['key' => 'field_luux_contact_strip_primary_link', 'name' => 'primary_link', 'type' => 'link'],
    ['key' => 'field_luux_contact_strip_secondary_link', 'name' => 'secondary_link', 'type' => 'link'],
]);

luux_layout_save_register_homepage('careers_teaser', [
    ['key' => 'field_luux_careers_teaser_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_careers_teaser_text', 'name' => 'text', 'type' => 'scalar'],
    ['key' => 'field_luux_careers_teaser_cta', 'name' => 'cta', 'type' => 'link'],
]);

luux_layout_save_register_homepage('social_grid', [
    ['key' => 'field_luux_social_grid_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_social_grid_post_count', 'name' => 'post_count', 'type' => 'number'],
]);

luux_layout_save_register_homepage('usps', [
    ['key' => 'field_luux_usps_section_label', 'name' => 'section_label', 'type' => 'scalar'],
    ['key' => 'field_luux_usps_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_usps_light_background', 'name' => 'light_background', 'type' => 'true_false'],
    ['key' => 'field_luux_usps_section_id', 'name' => 'section_id', 'type' => 'scalar'],
], [
    [
        'name'       => 'items',
        'key'        => 'field_luux_usps_items',
        'sub_fields' => [
            ['key' => 'field_luux_usps_icon', 'name' => 'icon', 'type' => 'image'],
            ['key' => 'field_luux_usps_title', 'name' => 'title', 'type' => 'scalar'],
            ['key' => 'field_luux_usps_description', 'name' => 'description', 'type' => 'scalar'],
        ],
    ],
]);

luux_layout_save_register_homepage('group_section', [
    ['key' => 'field_luux_group_section_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_group_section_heading_lead', 'name' => 'heading_lead', 'type' => 'scalar'],
    ['key' => 'field_luux_group_section_heading_logo', 'name' => 'heading_logo', 'type' => 'image'],
    ['key' => 'field_luux_group_section_heading_trail', 'name' => 'heading_trail', 'type' => 'scalar'],
    ['key' => 'field_luux_group_section_text', 'name' => 'text', 'type' => 'scalar'],
], [
    [
        'name'       => 'logos',
        'key'        => 'field_luux_group_section_logos',
        'sub_fields' => [
            ['key' => 'field_luux_group_section_logo_image', 'name' => 'image', 'type' => 'image'],
        ],
    ],
]);

luux_layout_save_register_homepage('featured_offers', [
    ['key' => 'field_luux_featured_offers_section_label', 'name' => 'section_label', 'type' => 'scalar'],
    ['key' => 'field_luux_featured_offers_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_featured_offers_intro', 'name' => 'intro', 'type' => 'scalar'],
], [
    [
        'name'       => 'offers',
        'key'        => 'field_luux_featured_offers_offers',
        'sub_fields' => [
            ['key' => 'field_luux_featured_offers_image', 'name' => 'image', 'type' => 'image'],
            ['key' => 'field_luux_featured_offers_title', 'name' => 'title', 'type' => 'scalar'],
            ['key' => 'field_luux_featured_offers_description', 'name' => 'description', 'type' => 'scalar'],
            ['key' => 'field_luux_featured_offers_price', 'name' => 'price', 'type' => 'scalar'],
            ['key' => 'field_luux_featured_offers_link', 'name' => 'link', 'type' => 'link'],
        ],
    ],
]);

luux_layout_save_register_homepage('travel_style', [
    ['key' => 'field_luux_travel_style_section_label', 'name' => 'section_label', 'type' => 'scalar'],
    ['key' => 'field_luux_travel_style_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_travel_style_footer_heading', 'name' => 'footer_heading', 'type' => 'scalar'],
    ['key' => 'field_luux_travel_style_cta', 'name' => 'cta', 'type' => 'link'],
], [
    [
        'name'       => 'categories',
        'key'        => 'field_luux_travel_style_categories',
        'sub_fields' => [
            ['key' => 'field_luux_travel_style_image', 'name' => 'image', 'type' => 'image'],
            ['key' => 'field_luux_travel_style_title', 'name' => 'title', 'type' => 'scalar'],
        ],
    ],
]);

luux_layout_save_register_homepage('destinations', [
    ['key' => 'field_luux_destinations_section_label', 'name' => 'section_label', 'type' => 'scalar'],
    ['key' => 'field_luux_destinations_heading', 'name' => 'heading', 'type' => 'scalar'],
], [
    [
        'name'       => 'destinations',
        'key'        => 'field_luux_destinations_destinations',
        'sub_fields' => [
            ['key' => 'field_luux_destinations_image', 'name' => 'image', 'type' => 'image'],
            ['key' => 'field_luux_destinations_title', 'name' => 'title', 'type' => 'scalar'],
            ['key' => 'field_luux_destinations_link', 'name' => 'link', 'type' => 'link'],
        ],
    ],
]);

luux_layout_save_register_homepage('reviews', [
    ['key' => 'field_luux_reviews_heading', 'name' => 'heading', 'type' => 'scalar'],
    ['key' => 'field_luux_reviews_view_all_link', 'name' => 'view_all_link', 'type' => 'link'],
], [
    [
        'name'       => 'testimonials',
        'key'        => 'field_luux_reviews_testimonials',
        'sub_fields' => [
            ['key' => 'field_luux_reviews_rating', 'name' => 'rating', 'type' => 'number'],
            ['key' => 'field_luux_reviews_quote', 'name' => 'quote', 'type' => 'scalar'],
            ['key' => 'field_luux_reviews_name', 'name' => 'name', 'type' => 'scalar'],
            ['key' => 'field_luux_reviews_date', 'name' => 'date', 'type' => 'scalar'],
        ],
    ],
]);
