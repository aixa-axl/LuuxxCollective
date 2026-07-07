<?php
/**
 * Layout: contact-hero — Figma 76:5683
 */

$heading    = get_sub_field('heading');
$image_id   = get_sub_field('background_image');
$section_id = get_sub_field('section_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="contact-hero relative flex h-[26.25rem] items-center justify-center overflow-hidden lg:h-[30.625rem]">
    <?php if ($image_id) : ?>
        <?php echo wp_get_attachment_image($image_id, 'full', false, [
            'class'         => 'absolute inset-0 h-full w-full object-cover',
            'fetchpriority' => 'high',
        ]); ?>
        <div class="contact-hero__scrim absolute inset-0" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if ($heading) : ?>
        <div class="container-site relative z-10 flex justify-center">
            <h1 class="max-w-[42.5rem] text-center font-display text-[2.5rem] leading-none text-brand-white lg:text-h1 lg:leading-[0.88]">
                <?php echo esc_html($heading); ?>
            </h1>
        </div>
    <?php endif; ?>
</section>
