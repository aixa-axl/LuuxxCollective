<?php
/**
 * Layout: reviews
 */

$heading      = luux_sub_field('heading');
$view_all     = get_sub_field('view_all_link');
$testimonials = get_sub_field('testimonials');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
) {
    if (function_exists('luux_reviews_link_from_meta')) {
        $link_from_meta = luux_reviews_link_from_meta((int) $post_id, $row_index);

        if ($link_from_meta !== null) {
            $view_all = $link_from_meta;
        }
    }

    if (function_exists('luux_reviews_testimonials_from_meta')) {
        $from_meta = luux_reviews_testimonials_from_meta((int) $post_id, $row_index);

        if ($from_meta !== []) {
            $testimonials = $from_meta;
        }
    }
}

if (is_array($view_all) && ! empty($view_all['title'])) {
    $view_all['title'] = str_replace(['\\u2192', 'u2192'], '→', (string) $view_all['title']);
}
?>

<section class="bg-brand-dark section-pad">
    <div class="container-site flex flex-col gap-8 lg:gap-16">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <?php if ($heading) : ?>
                <h2 class="font-display text-h3 leading-[1.1] text-brand-white lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if (! empty($view_all['url'])) : ?>
                <a class="link-underline hidden text-brand-white lg:inline-flex"
                   href="<?php echo esc_url($view_all['url']); ?>"
                   <?php echo ! empty($view_all['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($view_all['title']); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($testimonials) : ?>
            <div class="reviews__carousel" data-reviews-carousel>
                <div class="reviews__viewport">
                    <div class="reviews__track flex gap-4 lg:grid lg:grid-cols-3 lg:gap-8">
                        <?php foreach ($testimonials as $item) : ?>
                            <blockquote class="reviews__card flex w-full flex-shrink-0 flex-col gap-5 rounded border border-brand-gold p-6 lg:w-auto lg:border-0 lg:p-8">
                                <?php if (! empty($item['rating'])) : ?>
                                    <p class="font-body text-body-sm text-brand-gold" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'luux'), $item['rating'])); ?>">
                                        <?php echo esc_html(str_repeat('★', (int) $item['rating'])); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (! empty($item['quote'])) : ?>
                                    <p class="font-display text-body-lg text-brand-white lg:text-quote"><?php echo esc_html($item['quote']); ?></p>
                                <?php endif; ?>
                                <footer class="flex flex-col gap-1 font-body text-body text-brand-white">
                                    <?php if (! empty($item['name'])) : ?>
                                        <cite class="not-italic"><?php echo esc_html($item['name']); ?></cite>
                                    <?php endif; ?>
                                    <?php if (! empty($item['date'])) : ?>
                                        <time class="opacity-60"><?php echo esc_html($item['date']); ?></time>
                                    <?php endif; ?>
                                </footer>
                            </blockquote>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="reviews__dots lg:hidden" data-reviews-dots role="tablist" aria-label="<?php esc_attr_e('Reviews', 'luux'); ?>"></div>
            </div>
        <?php endif; ?>

        <?php if (! empty($view_all['url'])) : ?>
            <a class="link-underline-block link-underline-block--ruled text-brand-white lg:hidden"
               href="<?php echo esc_url($view_all['url']); ?>"
               <?php echo ! empty($view_all['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo esc_html($view_all['title']); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
