<?php
/**
 * Layout: travel-style
 */

$label          = get_sub_field('section_label');
$heading        = get_sub_field('heading');
$categories     = get_sub_field('categories');
$footer_heading = get_sub_field('footer_heading');
$cta            = get_sub_field('cta');
?>

<section class="bg-brand-cream-light section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($label || $heading) : ?>
            <div class="section-heading items-center text-center">
                <?php if ($label) : ?>
                    <p class="font-display text-body uppercase text-brand-gold"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 leading-[1.1] text-brand-dark lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($categories) : ?>
            <div class="flex flex-col gap-6 lg:grid lg:grid-cols-7 lg:gap-4">
                <?php foreach ($categories as $category) : ?>
                    <article class="flex flex-col gap-3 lg:gap-2.5">
                        <?php if (! empty($category['image'])) : ?>
                            <div class="relative h-80 overflow-hidden rounded bg-brand-cream-light lg:aspect-[264/387] lg:h-auto">
                                <?php echo wp_get_attachment_image($category['image'], 'medium_large', false, [
                                    'class'   => 'h-full w-full object-cover',
                                    'loading' => 'lazy',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($category['title'])) : ?>
                            <h3 class="text-center font-display text-body-lg text-brand-dark lg:text-h3"><?php echo esc_html($category['title']); ?></h3>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($footer_heading || ! empty($cta['url'])) : ?>
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <?php if ($footer_heading) : ?>
                    <p class="font-display text-h3 text-brand-dark lg:max-w-xl"><?php echo esc_html($footer_heading); ?></p>
                <?php endif; ?>
                <?php if (! empty($cta['url'])) : ?>
                    <a class="link-underline-block link-underline-block--ruled w-full text-brand-dark lg:w-fit"
                       href="<?php echo esc_url($cta['url']); ?>"
                       <?php echo ! empty($cta['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($cta['title']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
