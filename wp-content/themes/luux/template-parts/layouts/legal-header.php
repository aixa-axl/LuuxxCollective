<?php
/**
 * Layout: legal-header — page title + intro for Terms / Privacy / Cookies.
 */

$heading    = luux_sub_field('heading');
$intro      = luux_sub_field('intro');
$section_id = luux_sub_field('section_id');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

// Always prefer direct postmeta when present — legal scalars are custom-saved.
if ($post_id && $row_index >= 0 && function_exists('luux_read_section_meta')) {
    foreach (['heading', 'intro', 'section_id'] as $name) {
        $from_meta = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($from_meta !== null && $from_meta !== '') {
            ${$name} = $from_meta;
        }
    }
}

if (! $heading && ! $intro) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="legal-header section-pad bg-brand-cream-light">
    <div class="container-site flex flex-col items-start gap-6 lg:gap-8">
        <?php if ($heading) : ?>
            <h1 class="max-w-3xl font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h1>
        <?php endif; ?>
        <?php if ($intro) : ?>
            <div class="legal-content max-w-3xl">
                <?php echo wp_kses_post($intro); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
