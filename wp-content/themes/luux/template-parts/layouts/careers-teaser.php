<?php
/**
 * Layout: careers-teaser
 */

$heading = luux_sub_field('heading');
$text    = luux_sub_field('text');
$cta     = luux_sub_field_link('cta');
?>

<section class="border-y border-brand-cream bg-brand-cream-light section-pad">
    <div class="container-site flex flex-col items-center gap-8 text-center">
        <?php if ($heading || $text) : ?>
            <div class="flex max-w-2xl flex-col gap-3">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 leading-[1.1] text-brand-dark lg:text-h3 lg:leading-none"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body text-brand-gold-muted"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (! empty($cta['url'])) : ?>
            <a class="link-underline w-full text-center text-brand-dark md:w-auto md:text-left"
               href="<?php echo esc_url($cta['url']); ?>"
               <?php echo ! empty($cta['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo esc_html($cta['title']); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
