<?php
/**
 * Layout: trust-strip
 */

$rating      = luux_sub_field('rating');
$press_logos = get_sub_field('press_logos');
$award       = luux_sub_field('award');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_trust_strip_press_logos_from_meta')
) {
    $from_meta = luux_trust_strip_press_logos_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $press_logos = $from_meta;
    }
}
?>

<section class="trust-strip bg-brand-white">
    <div class="container-site trust-strip__inner">
        <?php if ($rating) : ?>
            <p class="trust-strip__rating font-body text-body text-brand-primary"><?php echo esc_html($rating); ?></p>
        <?php endif; ?>

        <?php if ($press_logos) : ?>
            <div class="trust-strip__divider" aria-hidden="true"></div>
            <ul class="trust-strip__press">
                <?php foreach ($press_logos as $logo) :
                    if (empty($logo['name'])) continue;
                    ?>
                    <li class="font-body text-caption font-black uppercase text-brand-primary"><?php echo esc_html($logo['name']); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($award) : ?>
            <div class="trust-strip__divider" aria-hidden="true"></div>
            <p class="trust-strip__award font-body text-body text-brand-primary"><?php echo esc_html($award); ?></p>
        <?php endif; ?>
    </div>
</section>
