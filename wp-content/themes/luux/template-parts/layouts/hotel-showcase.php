<?php
/**
 * Layout: hotel-showcase
 */

$heading    = get_sub_field('heading');
$intro      = get_sub_field('intro');
$footnote   = get_sub_field('footnote');
$hotels     = get_sub_field('hotels');
$section_id = get_sub_field('section_id');

if (! $hotels) {
    return;
}

$panel_id = 'hotel-showcase-' . get_row_index();
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="hotel-showcase section-pad bg-brand-cream-light" data-hotel-showcase>
    <div class="container-site flex flex-col gap-10 lg:gap-12">
        <?php if ($heading || $intro || $footnote) : ?>
            <div class="mx-auto flex max-w-3xl flex-col gap-6 text-left lg:gap-12 lg:text-center">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($intro) : ?>
                    <p class="font-display text-body-lg text-brand-gold-muted lg:text-h3"><?php echo esc_html($intro); ?></p>
                <?php endif; ?>
                <?php if ($footnote) : ?>
                    <p class="font-display text-body text-brand-gold-muted"><?php echo esc_html($footnote); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($hotels) > 1) : ?>
            <div class="hotel-showcase__pills" role="tablist" aria-label="<?php esc_attr_e('Hotels', 'luux'); ?>">
                <?php foreach ($hotels as $i => $hotel) :
                    if (empty($hotel['name'])) continue;
                    ?>
                    <button type="button"
                            class="hotel-showcase__pill"
                            role="tab"
                            id="<?php echo esc_attr($panel_id . '-tab-' . $i); ?>"
                            aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo esc_attr($panel_id . '-panel-' . $i); ?>"
                            data-hotel-tab="<?php echo esc_attr((string) $i); ?>">
                        <?php echo esc_html($hotel['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="hotel-showcase__viewport">
            <div class="hotel-showcase__track" data-hotel-track>
            <?php foreach ($hotels as $i => $hotel) : ?>
                <article class="hotel-showcase__slide"
                         role="tabpanel"
                         id="<?php echo esc_attr($panel_id . '-panel-' . $i); ?>"
                         aria-labelledby="<?php echo esc_attr($panel_id . '-tab-' . $i); ?>"
                         <?php echo $i === 0 ? '' : 'aria-hidden="true"'; ?>>
                    <div class="hotel-showcase__card">
                        <?php
                        $hotel_image = ! empty($hotel['hotel_image']) ? $hotel['hotel_image'] : ($hotel['image'] ?? 0);
                        if ($hotel_image) : ?>
                            <div class="hotel-showcase__media">
                                <?php echo wp_get_attachment_image($hotel_image, 'large', false, [
                                    'class'   => 'h-full w-full object-cover',
                                    'loading' => 'lazy',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <div class="hotel-showcase__content">
                            <?php if (! empty($hotel['name'])) : ?>
                                <h3 class="font-display text-h3 text-brand-dark"><?php echo esc_html($hotel['name']); ?></h3>
                            <?php endif; ?>
                            <?php if (! empty($hotel['description'])) : ?>
                                <p class="font-body text-body-lg text-brand-gold-muted"><?php echo esc_html($hotel['description']); ?></p>
                            <?php endif; ?>
                            <?php if (! empty($hotel['inclusions'])) : ?>
                                <ul class="hotel-showcase__inclusions">
                                    <?php foreach ($hotel['inclusions'] as $row) :
                                        if (empty($row['text'])) continue;
                                        ?>
                                        <li class="font-body text-body text-brand-dark">
                                            <span class="text-brand-gold-muted" aria-hidden="true">✓</span>
                                            <?php echo esc_html($row['text']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (! empty($hotel['cta']['url'])) : ?>
                                <a class="link-underline self-start text-brand-dark"
                                   href="<?php echo esc_url($hotel['cta']['url']); ?>"
                                   <?php echo ! empty($hotel['cta']['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                                    <?php echo esc_html($hotel['cta']['title']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div>

        <?php if (count($hotels) > 1) : ?>
            <div class="hotel-showcase__dots" data-hotel-dots aria-hidden="true"></div>
        <?php endif; ?>
    </div>
</section>
