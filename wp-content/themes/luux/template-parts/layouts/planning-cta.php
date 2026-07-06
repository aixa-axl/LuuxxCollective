<?php
/**
 * Layout: planning-cta
 */

$heading  = get_sub_field('heading');
$text     = get_sub_field('text');
$primary  = get_sub_field('primary_cta');
$links    = get_sub_field('secondary_links');
$section_id = get_sub_field('section_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="planning-cta">
    <div class="container-site planning-cta__inner">
        <?php if ($heading || $text) : ?>
            <div class="planning-cta__copy">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-white lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body text-brand-primary-soft"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (! empty($primary['url'])) : ?>
            <a class="btn btn-outline planning-cta__primary"
               href="<?php echo esc_url($primary['url']); ?>"
               <?php echo ! empty($primary['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo esc_html($primary['title']); ?>
            </a>
        <?php endif; ?>

        <?php if ($links) : ?>
            <nav class="planning-cta__links" aria-label="<?php esc_attr_e('Contact options', 'luux'); ?>">
                <?php foreach ($links as $link) :
                    if (empty($link['link']['url'])) continue;
                    $item = $link['link'];
                    ?>
                    <a class="planning-cta__link"
                       href="<?php echo esc_url($item['url']); ?>"
                       <?php echo ! empty($item['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($item['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
