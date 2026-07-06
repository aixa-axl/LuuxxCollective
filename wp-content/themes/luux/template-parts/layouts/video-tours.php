<?php
/**
 * Layout: video-tours
 */

$heading     = get_sub_field('heading');
$text        = get_sub_field('text');
$image_left  = get_sub_field('image_left');
$image_right = get_sub_field('image_right');
$section_id  = get_sub_field('section_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="video-tours section-pad">
    <div class="container-site video-tours__grid">
        <?php if ($image_left) : ?>
            <div class="video-tours__media">
                <?php echo wp_get_attachment_image($image_left, 'large', false, [
                    'class'   => 'h-full w-full object-cover',
                    'loading' => 'lazy',
                ]); ?>
            </div>
        <?php endif; ?>

        <?php if ($heading || $text) : ?>
            <div class="video-tours__copy">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body text-brand-primary"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($image_right) : ?>
            <div class="video-tours__media">
                <?php echo wp_get_attachment_image($image_right, 'large', false, [
                    'class'   => 'h-full w-full object-cover',
                    'loading' => 'lazy',
                ]); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
