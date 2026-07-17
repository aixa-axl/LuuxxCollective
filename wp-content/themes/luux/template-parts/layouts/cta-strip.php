<?php
/**
 * Layout: cta-strip
 */

$text           = luux_sub_field('text');
$primary_link   = get_sub_field('primary_link');
$secondary_link = get_sub_field('secondary_link');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_cta_strip_link_from_meta')
) {
    $primary_from_meta = luux_cta_strip_link_from_meta((int) $post_id, $row_index, 'primary_link');

    if ($primary_from_meta !== null) {
        $primary_link = $primary_from_meta;
    }

    $secondary_from_meta = luux_cta_strip_link_from_meta((int) $post_id, $row_index, 'secondary_link');

    if ($secondary_from_meta !== null) {
        $secondary_link = $secondary_from_meta;
    }
}
?>

<section class="bg-brand-dark p-10 lg:section-pad">
    <div class="container-site flex flex-col items-center gap-8 text-center lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:text-left">
        <?php if ($text) : ?>
            <p class="font-body text-body-lg text-brand-white lg:max-w-none"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if ($primary_link || $secondary_link) : ?>
            <div class="flex w-full flex-col items-center gap-4 lg:w-auto lg:flex-row lg:gap-10">
                <?php if (! empty($primary_link['url'])) :
                    $primary_title = str_replace(['\\u2192', 'u2192'], '→', (string) ($primary_link['title'] ?? ''));
                    ?>
                    <a class="link-underline-block link-underline-block--ruled text-brand-white"
                       href="<?php echo esc_url($primary_link['url']); ?>"
                       <?php echo ! empty($primary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($primary_title); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($secondary_link['url'])) :
                    $secondary_title = str_replace(['\\u2192', 'u2192'], '→', (string) ($secondary_link['title'] ?? ''));
                    ?>
                    <a class="link-underline-block font-ui text-body text-brand-cream lg:py-3"
                       href="<?php echo esc_url($secondary_link['url']); ?>"
                       <?php echo ! empty($secondary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($secondary_title); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
