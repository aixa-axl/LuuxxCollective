<?php
/**
 * Layout: travel-style
 */

$label          = get_sub_field('section_label');
$heading        = get_sub_field('heading');
$categories     = get_sub_field('categories');
$footer_heading = get_sub_field('footer_heading');
$cta            = get_sub_field('cta');
$slide_count    = is_array($categories) ? count($categories) : 0;
?>

<section class="bg-brand-cream-light section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($label || $heading) : ?>
            <div class="section-heading items-center text-center">
                <?php if ($label) : ?>
                    <p class="font-display text-body uppercase text-brand-gold-muted"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 leading-[1.1] text-brand-dark lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($categories) : ?>
            <div class="travel-carousel flex flex-col gap-6 lg:gap-10" data-travel-carousel>
                <div class="travel-carousel__viewport"
                     tabindex="0"
                     aria-roledescription="carousel"
                     aria-label="<?php esc_attr_e('Travel styles', 'luux'); ?>">
                    <div class="travel-carousel__track">
                        <?php foreach ($categories as $category) : ?>
                            <article class="travel-carousel__slide">
                                <?php if (! empty($category['image'])) : ?>
                                    <div class="travel-carousel__media">
                                        <?php echo wp_get_attachment_image($category['image'], 'medium_large', false, [
                                            'class'   => 'h-full w-full object-cover',
                                            'loading' => 'lazy',
                                        ]); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (! empty($category['title'])) : ?>
                                    <h3 class="travel-carousel__title"><?php echo esc_html($category['title']); ?></h3>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($slide_count > 1) : ?>
                    <div class="travel-carousel__dots hidden lg:flex" role="tablist" aria-label="<?php esc_attr_e('Carousel pagination', 'luux'); ?>">
                        <?php for ($i = 0; $i < $slide_count; $i++) : ?>
                            <button type="button"
                                    class="travel-carousel__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                    role="tab"
                                    aria-label="<?php echo esc_attr(sprintf(__('Go to slide %d', 'luux'), $i + 1)); ?>"
                                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                                    data-travel-carousel-dot="<?php echo esc_attr((string) $i); ?>">
                            </button>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
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
