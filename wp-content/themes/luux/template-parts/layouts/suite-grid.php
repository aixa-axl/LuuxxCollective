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

$panel_id = 'suite-grid-' . get_row_index();
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="suite-grid section-pad" data-suite-filter="<?php echo esc_attr($panel_id); ?>">
    <div class="container-site flex flex-col gap-10 lg:gap-12">
        <div class="flex flex-col gap-3">
            <?php if ($section_label) : ?>
                <p class="font-body text-body uppercase text-brand-gold"><?php echo esc_html($section_label); ?></p>
            <?php endif; ?>
            <?php if ($heading) : ?>
                <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
        </div>

        <?php if ($categories && count($categories) > 1) : ?>
            <div class="suite-grid__filters">
                <?php if ($filter_label) : ?>
                    <p class="suite-grid__filter-label font-body text-body-lg text-brand-dark"><?php echo esc_html($filter_label); ?></p>
                <?php endif; ?>
                <div class="suite-grid__pills" role="tablist">
                    <?php foreach ($categories as $i => $cat) :
                        if (empty($cat['name'])) continue;
                        $slug = sanitize_title($cat['name']);
                        ?>
                        <button type="button"
                                class="suite-grid__pill"
                                role="tab"
                                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                                data-suite-filter-trigger="<?php echo esc_attr($slug); ?>">
                            <?php echo esc_html($cat['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="suite-grid__cards">
            <?php foreach ($suites as $i => $suite) :
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
                                <svg class="size-3.5 shrink-0" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                    <path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
