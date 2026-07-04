<?php
/**
 * Layout: usps
 */

$label   = get_sub_field('section_label');
$heading = get_sub_field('heading');
$items   = get_sub_field('items');
?>

<section class="bg-brand-cream-light section-pad">
    <div class="container-site flex flex-col gap-12 lg:flex-row lg:gap-32">
        <?php if ($label || $heading) : ?>
            <div class="flex max-w-sm shrink-0 flex-col gap-6">
                <?php if ($label) : ?>
                    <p class="font-body text-body uppercase text-brand-gold"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h2 text-brand-dark"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($items) : ?>
            <div class="grid flex-1 gap-8 pt-0 lg:grid-cols-2 lg:gap-8 lg:pt-10">
                <?php foreach ($items as $item) : ?>
                    <article class="flex flex-col items-center gap-6 text-center">
                        <?php if (! empty($item['icon'])) : ?>
                            <div class="size-10">
                                <?php echo wp_get_attachment_image($item['icon'], 'thumbnail', false, [
                                    'class' => 'h-full w-full object-contain',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex flex-col gap-2">
                            <?php if (! empty($item['title'])) : ?>
                                <h3 class="font-display text-h3 text-brand-dark"><?php echo esc_html($item['title']); ?></h3>
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
