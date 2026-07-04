<?php
/**
 * Layout: careers-teaser
 */

$heading = get_sub_field('heading');
$text    = get_sub_field('text');
$cta     = get_sub_field('cta');
?>

<section class="border-y border-brand-cream bg-brand-cream-light section-pad">
    <div class="container-site flex flex-col items-center gap-8 text-center">
        <?php if ($heading || $text) : ?>
            <div class="flex max-w-2xl flex-col gap-3">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-dark"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body text-brand-gold-muted"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (! empty($cta['url'])) : ?>
            <a class="link-underline text-brand-dark"
               href="<?php echo esc_url($cta['url']); ?>"
               <?php echo ! empty($cta['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo esc_html($cta['title']); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
