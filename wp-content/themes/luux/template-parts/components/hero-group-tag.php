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
?>

<div class="site-header__group-tag pointer-events-none">
    <div class="site-header__group-tag-label inline-flex max-w-[calc(100%-1.25rem)] items-center gap-x-1.5 rounded-bl-2xl rounded-br-2xl bg-brand-primary py-2.5 pr-5 pl-5 font-display text-body-sm text-brand-white sm:max-w-none md:gap-x-2 md:pr-6 lg:py-3 lg:pr-8 lg:pl-[var(--spacing-gutter)]">
        <span><?php esc_html_e('Part of the', 'luux'); ?></span>
        <?php if ($group_tag_logo) :
            $logo_mime = get_post_mime_type($group_tag_logo);
            $logo_atts = [
                'class'    => 'site-header__group-tag-logo',
                'loading'  => 'lazy',
                'decoding' => 'async',
            ];
            if ($logo_mime === 'image/svg+xml') {
                $logo_url = wp_get_attachment_url($group_tag_logo);
                if ($logo_url) :
                    $logo_alt = get_post_meta($group_tag_logo, '_wp_attachment_image_alt', true);
                    ?>
                    <img class="site-header__group-tag-logo"
                         src="<?php echo esc_url($logo_url); ?>"
                         alt="<?php echo esc_attr($logo_alt ?: __('TravelSeen', 'luux')); ?>"
                         loading="lazy"
                         decoding="async">
                <?php endif;
            } else {
                echo wp_get_attachment_image($group_tag_logo, 'full', false, $logo_atts);
            }
        endif; ?>
        <span><?php esc_html_e('Group', 'luux'); ?></span>
    </div>
</div>
