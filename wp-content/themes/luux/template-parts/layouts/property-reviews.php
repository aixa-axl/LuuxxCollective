<?php
/**
 * Layout: property-reviews
 */

$heading      = get_sub_field('heading');
$view_all     = get_sub_field('view_all_link');
$testimonials = get_sub_field('testimonials');
$section_id   = get_sub_field('section_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="property-reviews section-pad">
    <div class="container-site flex flex-col gap-8 lg:gap-16">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <?php if ($heading) : ?>
                <h2 class="font-display text-h3 leading-[1.1] text-brand-primary lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if (! empty($view_all['url'])) : ?>
                <a class="link-underline hidden text-brand-primary lg:inline-flex"
                   href="<?php echo esc_url($view_all['url']); ?>"
                   <?php echo ! empty($view_all['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($view_all['title']); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($testimonials) : ?>
            <div class="flex flex-col gap-6 lg:grid lg:grid-cols-3 lg:gap-8">
                <?php foreach ($testimonials as $i => $item) : ?>
                    <blockquote class="<?php echo $i === 0 ? 'flex' : 'hidden lg:flex'; ?> property-reviews__card flex-col gap-5">
                        <?php if (! empty($item['rating'])) : ?>
                            <p class="font-body text-body-sm text-brand-gold" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'luux'), $item['rating'])); ?>">
                                <?php echo esc_html(str_repeat('★', (int) $item['rating'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (! empty($item['quote'])) : ?>
                            <p class="font-display text-body-lg text-brand-primary lg:text-quote"><?php echo esc_html($item['quote']); ?></p>
                        <?php endif; ?>
                        <footer class="flex flex-col gap-1 font-body text-body text-brand-primary">
                            <?php if (! empty($item['name'])) : ?>
                                <cite class="not-italic"><?php echo esc_html($item['name']); ?></cite>
                            <?php endif; ?>
                            <?php if (! empty($item['date'])) : ?>
                                <time class="text-brand-primary-muted"><?php echo esc_html($item['date']); ?></time>
                            <?php endif; ?>
                        </footer>
                    </blockquote>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (! empty($view_all['url'])) : ?>
            <a class="link-underline-block link-underline-block--ruled text-brand-primary lg:hidden"
               href="<?php echo esc_url($view_all['url']); ?>"
               <?php echo ! empty($view_all['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo esc_html($view_all['title']); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
