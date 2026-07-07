<?php
/**
 * Layout: suite-grid
 */

$section_label = get_sub_field('section_label');
$heading       = get_sub_field('heading');
$filter_label  = get_sub_field('filter_label');
$categories    = get_sub_field('categories');
$suites        = get_sub_field('suites');
$section_id    = get_sub_field('section_id');

if (! $suites) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="suite-grid section-pad" data-suite-grid>
    <div class="container-site flex flex-col gap-10 lg:gap-12">
        <div class="suite-grid__header">
            <div class="flex flex-col gap-3">
                <?php if ($section_label) : ?>
                    <p class="font-body text-body uppercase text-brand-gold"><?php echo esc_html($section_label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($categories && count($categories) > 1) : ?>
                <div class="suite-grid__filters">
                    <?php if ($filter_label) : ?>
                        <p class="suite-grid__filter-label font-body text-body-lg text-brand-dark"><?php echo esc_html($filter_label); ?></p>
                    <?php endif; ?>
                    <div class="suite-grid__pills" role="tablist">
                        <?php foreach ($categories as $cat) :
                            if (empty($cat['name'])) continue;
                            $slug = sanitize_title($cat['name']);
                            ?>
                            <button type="button"
                                    class="suite-grid__pill"
                                    role="tab"
                                    aria-selected="false"
                                    data-suite-filter-trigger="<?php echo esc_attr($slug); ?>">
                                <?php echo esc_html($cat['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            </div> 
        </div>

        <div class="suite-grid__carousel"
             tabindex="0"
             aria-roledescription="carousel"
             aria-label="<?php esc_attr_e('Suites', 'luux'); ?>">
            <div class="suite-grid__viewport">
                <div class="suite-grid__track">
                    <?php foreach ($suites as $suite) :
                        $cat_slug = ! empty($suite['category']) ? sanitize_title($suite['category']) : '';
                        $offset   = $suite['vertical_offset'] ?? 'none';
                        ?>
                        <article class="suite-grid__card suite-grid__card--offset-<?php echo esc_attr($offset); ?>"
                                 data-suite-category="<?php echo esc_attr($cat_slug); ?>">
                            <?php if (! empty($suite['image'])) : ?>
                                <div class="suite-grid__media">
                                    <?php echo wp_get_attachment_image($suite['image'], 'large', false, [
                                        'class'   => 'h-full w-full object-cover',
                                        'loading' => 'lazy',
                                    ]); ?>
                                </div>
                            <?php endif; ?>
                            <div class="suite-grid__body">
                                <?php if (! empty($suite['hotel_label'])) : ?>
                                    <p class="font-body text-body uppercase text-brand-gold-muted"><?php echo esc_html($suite['hotel_label']); ?></p>
                                <?php endif; ?>
                                <?php if (! empty($suite['title'])) : ?>
                                    <h3 class="font-display text-h3 text-brand-dark"><?php echo esc_html($suite['title']); ?></h3>
                                <?php endif; ?>
                                <?php if (! empty($suite['description'])) : ?>
                                    <p class="font-body text-body text-brand-gold-muted"><?php echo esc_html($suite['description']); ?></p>
                                <?php endif; ?>
                                <?php if (! empty($suite['link']['url'])) : ?>
                                    <a class="suite-grid__link font-display text-body text-brand-dark"
                                       href="<?php echo esc_url($suite['link']['url']); ?>"
                                       <?php echo ! empty($suite['link']['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                                        <?php echo esc_html($suite['link']['title']); ?>

                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="suite-grid__dots" role="tablist" aria-label="<?php esc_attr_e('Carousel pagination', 'luux'); ?>" hidden></div>
        </div>
    </div>
</section>
