<?php
/**
 * Layout: social-grid
 */

$heading = get_sub_field('heading');
$images  = get_sub_field('images');
?>

<section class="section-pad">
    <div class="container-site flex flex-col gap-10">
        <?php if ($heading) : ?>
            <h2 class="max-w-sm font-display text-h2 text-brand-dark"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <?php if ($images) : ?>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4 lg:gap-4">
                <?php foreach ($images as $image_id) : ?>
                    <div class="relative aspect-square overflow-hidden bg-brand-cream-light">
                        <?php echo wp_get_attachment_image($image_id, 'medium_large', false, [
                            'class'   => 'h-full w-full object-cover',
                            'loading' => 'lazy',
                        ]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
