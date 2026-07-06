<?php
/**
 * Layout: dining
 */

$section_label = get_sub_field('section_label');
$heading       = get_sub_field('heading');
$text          = get_sub_field('text');
$highlights    = get_sub_field('highlights');
$image_hero    = get_sub_field('image_hero');
$image_left    = get_sub_field('image_left');
$image_top     = get_sub_field('image_top');
$image_bottom  = get_sub_field('image_bottom');
$section_id    = get_sub_field('section_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="dining section-pad bg-brand-cream-light">
    <div class="container-site dining__layout">
        <div class="dining__intro">
            <?php if ($section_label) : ?>
                <p class="font-body text-body uppercase text-brand-gold"><?php echo esc_html($section_label); ?></p>
            <?php endif; ?>
            <?php if ($heading) : ?>
                <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="font-body text-body-lg text-brand-gold-muted"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
            <?php if ($highlights) : ?>
                <ul class="dining__highlights">
                    <?php foreach ($highlights as $item) :
                        if (empty($item['text'])) continue;
                        ?>
                        <li class="font-body text-body text-brand-dark"><?php echo esc_html($item['text']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="dining__gallery" aria-hidden="false">
            <div class="dining__gallery-left">
                <?php if ($image_left) : ?>
                    <div class="dining__image dining__image--tall">
                        <?php echo wp_get_attachment_image($image_left, 'large', false, [
                            'class'   => 'h-full w-full object-cover',
                            'loading' => 'lazy',
                        ]); ?>
                    </div>
                <?php endif; ?>
                <div class="dining__gallery-stack">
                    <?php if ($image_top) : ?>
                        <div class="dining__image dining__image--mid">
                            <?php echo wp_get_attachment_image($image_top, 'medium_large', false, [
                                'class'   => 'h-full w-full object-cover',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($image_bottom) : ?>
                        <div class="dining__image dining__image--short">
                            <?php echo wp_get_attachment_image($image_bottom, 'medium_large', false, [
                                'class'   => 'h-full w-full object-cover',
                                'loading' => 'lazy',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($image_hero) : ?>
                <div class="dining__image dining__image--hero">
                    <?php echo wp_get_attachment_image($image_hero, 'large', false, [
                        'class'   => 'h-full w-full object-cover',
                        'loading' => 'lazy',
                    ]); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
