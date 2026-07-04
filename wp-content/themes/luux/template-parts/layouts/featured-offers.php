<?php
/**
 * Layout: featured-offers
 */

$label    = get_sub_field('section_label');
$heading  = get_sub_field('heading');
$intro    = get_sub_field('intro');
$offers   = get_sub_field('offers');
?>

<section class="section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($label || $heading || $intro) : ?>
            <div class="flex max-w-[40rem] flex-col gap-4">
                <?php if ($label) : ?>
                    <p class="font-display text-body uppercase text-brand-primary"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h2 text-brand-primary"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($intro) : ?>
                    <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($intro); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($offers) : ?>
            <div class="grid gap-8 lg:grid-cols-3">
                <?php foreach ($offers as $offer) : ?>
                    <article class="flex flex-col gap-6">
                        <?php if (! empty($offer['image'])) : ?>
                            <div class="relative aspect-[405/505] overflow-hidden bg-brand-cream-light">
                                <?php echo wp_get_attachment_image($offer['image'], 'large', false, [
                                    'class'   => 'h-full w-full object-cover',
                                    'loading' => 'lazy',
                                ]); ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-col gap-3">
                            <?php if (! empty($offer['title'])) : ?>
                                <h3 class="font-display text-h3 text-brand-primary"><?php echo esc_html($offer['title']); ?></h3>
                            <?php endif; ?>
                            <?php if (! empty($offer['description'])) : ?>
                                <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($offer['description']); ?></p>
                            <?php endif; ?>
                            <div class="flex items-end justify-between gap-4">
                                <?php if (! empty($offer['price'])) : ?>
                                    <p class="font-body text-body text-brand-primary"><?php echo esc_html($offer['price']); ?></p>
                                <?php endif; ?>
                                <?php if (! empty($offer['link']['url'])) : ?>
                                    <a class="link-underline shrink-0 text-brand-gold"
                                       href="<?php echo esc_url($offer['link']['url']); ?>"
                                       <?php echo ! empty($offer['link']['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                                        <?php echo esc_html($offer['link']['title'] ?: __('Discover This Offer →', 'luux')); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
