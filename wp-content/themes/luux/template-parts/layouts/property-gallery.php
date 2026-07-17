<?php
/**
 * Layout: property-gallery — Figma 76:3373
 */

$heading    = luux_sub_field('heading');
$text       = luux_sub_field('text');
$section_id = luux_sub_field('section_id');
$images     = get_sub_field('images');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_property_gallery_images_from_meta')
) {
    $from_meta = luux_property_gallery_images_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $images = $from_meta;
    }
}

if (! $images) {
    return;
}

$slots = [
    'col1-top'    => null,
    'col1-bottom' => null,
    'col2-top'    => null,
    'col2-bottom' => null,
    'col3-top'    => null,
    'col3-bottom' => null,
];

foreach ($images as $item) {
    if (empty($item['image']) || empty($item['size'])) {
        continue;
    }
    if (array_key_exists($item['size'], $slots)) {
        $slots[$item['size']] = (int) $item['image'];
    }
}

$columns = [
    1 => ['col1-top', 'col1-bottom'],
    2 => ['col2-top', 'col2-bottom'],
    3 => ['col3-top', 'col3-bottom'],
];
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="property-gallery section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($heading || $text) : ?>
            <div class="flex max-w-md flex-col gap-6 text-left lg:mx-auto lg:gap-10 lg:text-center">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="property-gallery__grid">
            <?php foreach ($columns as $col_num => $cell_keys) :
                $has_column = false;
                foreach ($cell_keys as $key) {
                    if ($slots[$key]) {
                        $has_column = true;
                        break;
                    }
                }
                if (! $has_column) {
                    continue;
                }
                ?>
                <div class="property-gallery__col property-gallery__col--<?php echo esc_attr((string) $col_num); ?>">
                    <?php foreach ($cell_keys as $key) :
                        if (! $slots[$key]) {
                            continue;
                        }
                        ?>
                        <div class="property-gallery__cell property-gallery__cell--<?php echo esc_attr($key); ?>">
                            <?php echo wp_get_attachment_image($slots[$key], 'large', false, [
                                'class'   => 'property-gallery__media',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
