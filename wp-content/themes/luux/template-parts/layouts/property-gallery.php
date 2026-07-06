<?php
/**
 * Layout: property-gallery
 */

$heading    = get_sub_field('heading');
$text       = get_sub_field('text');
$images     = get_sub_field('images');
$section_id = get_sub_field('section_id');

if (! $images) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="property-gallery section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($heading || $text) : ?>
            <div class="mx-auto flex max-w-md flex-col gap-6 text-center lg:gap-10">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="property-gallery__grid">
            <?php foreach ($images as $item) :
                if (empty($item['image'])) continue;
                $size = $item['size'] ?? 'medium';
                ?>
                <div class="property-gallery__cell property-gallery__cell--<?php echo esc_attr($size); ?>">
                    <?php echo wp_get_attachment_image($item['image'], 'large', false, [
                        'class'   => 'h-full w-full object-cover',
                        'loading' => 'lazy',
                    ]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
