<?php
/**
 * Layout: usps
 */

$label            = get_sub_field('section_label');
$heading          = get_sub_field('heading');
$items            = get_sub_field('items');
$light_background = get_sub_field('light_background');
$section_id       = get_sub_field('section_id');
$bg_class         = $light_background ? 'bg-brand-white' : 'bg-brand-cream-light';
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="<?php echo esc_attr($bg_class); ?> section-pad">
    <div class="container-site flex flex-col gap-10 lg:flex-row lg:gap-32">
        <?php if ($label || $heading) : ?>
            <div class="section-heading max-w-sm shrink-0 lg:gap-6">
                <?php if ($label) : ?>
                    <p class="font-ui font-semibold text-body uppercase text-brand-gold"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 leading-[1.1] text-brand-dark lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($items) : ?>
            <div class="flex flex-1 flex-col gap-8 lg:grid lg:grid-cols-2 lg:gap-8 lg:pt-10">
                <?php foreach ($items as $item) : ?>
                    <article class="flex flex-col gap-4 lg:items-center lg:gap-6 lg:text-center">
                        <?php if (! empty($item['icon'])) : ?>
                            <div class="size-8 lg:size-10">
                                <?php echo wp_get_attachment_image($item['icon'], 'thumbnail', false, [
                                    'class' => 'h-full w-full object-contain',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex flex-col gap-2">
                            <?php if (! empty($item['title'])) : ?>
                                <h3 class="font-display text-body-lg text-brand-dark lg:text-h3"><?php echo esc_html($item['title']); ?></h3>
                            <?php endif; ?>
                            <?php if (! empty($item['description'])) : ?>
                                <p class="font-body text-body-sm text-brand-gold-muted"><?php echo esc_html($item['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
