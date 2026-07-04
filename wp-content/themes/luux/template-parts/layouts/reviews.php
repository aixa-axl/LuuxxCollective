<?php
/**
 * Layout: reviews
 */

$heading      = get_sub_field('heading');
$view_all     = get_sub_field('view_all_link');
$testimonials = get_sub_field('testimonials');
?>

<section class="bg-brand-dark section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <?php if ($heading) : ?>
                <h2 class="font-display text-h2 text-brand-white"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if (! empty($view_all['url'])) : ?>
                <a class="link-underline text-brand-white"
                   href="<?php echo esc_url($view_all['url']); ?>"
                   <?php echo ! empty($view_all['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($view_all['title']); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($testimonials) : ?>
            <div class="grid gap-8 lg:grid-cols-3">
                <?php foreach ($testimonials as $item) : ?>
                    <blockquote class="flex flex-col gap-6 p-0 lg:p-8">
                        <?php if (! empty($item['rating'])) : ?>
                            <p class="font-body text-body-sm text-brand-gold" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'luux'), $item['rating'])); ?>">
                                <?php echo esc_html(str_repeat('★', (int) $item['rating'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (! empty($item['quote'])) : ?>
                            <p class="font-display text-quote text-brand-white"><?php echo esc_html($item['quote']); ?></p>
                        <?php endif; ?>
                        <footer class="flex flex-col gap-1 font-body text-body text-brand-white">
                            <?php if (! empty($item['name'])) : ?>
                                <cite class="not-italic"><?php echo esc_html($item['name']); ?></cite>
                            <?php endif; ?>
                            <?php if (! empty($item['date'])) : ?>
                                <time><?php echo esc_html($item['date']); ?></time>
                            <?php endif; ?>
                        </footer>
                    </blockquote>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
