<?php
/**
 * Component: hero group tag (homepage header)
 *
 * @var array $args {
 *     @type array $group_tag {
 *         @type bool $show
 *         @type int  $logo
 *     }
 * }
 */

$group_tag = $args['group_tag'] ?? [];
if (empty($group_tag['show'])) {
    return;
}

$group_tag_logo = (int) ($group_tag['logo'] ?? 0);
$logo_url         = '';
$logo_alt         = __('TravelSeen', 'luux');

if ($group_tag_logo) {
    $logo_url = wp_get_attachment_url($group_tag_logo) ?: '';
    $logo_alt = get_post_meta($group_tag_logo, '_wp_attachment_image_alt', true) ?: $logo_alt;
}

if (! $logo_url) {
    $logo_url = get_template_directory_uri() . '/assets/images/travelseen-logo.png';
}
?>

<div class="site-header__group-tag pointer-events-none">
    <div class="container-site">
        <div class="site-header__group-tag-label inline-flex w-fit max-w-full items-center rounded-bl-2xl rounded-br-2xl bg-brand-primary font-display text-body-sm text-brand-white">
            <span><?php esc_html_e('Part of the', 'luux'); ?></span>
            <img class="site-header__group-tag-logo"
                 src="<?php echo esc_url($logo_url); ?>"
                 alt="<?php echo esc_attr($logo_alt); ?>"
                 loading="lazy"
                 decoding="async">
            <span><?php esc_html_e('Group', 'luux'); ?></span>
        </div>
    </div>
</div>
