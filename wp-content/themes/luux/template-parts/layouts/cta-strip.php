<?php
/**
 * Layout: cta-strip
 */

$text          = get_sub_field('text');
$primary_link  = get_sub_field('primary_link');
$secondary_link = get_sub_field('secondary_link');
?>

<section class="bg-brand-dark section-pad">
    <div class="container-site flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <?php if ($text) : ?>
            <p class="font-body text-body-lg text-brand-white"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if ($primary_link || $secondary_link) : ?>
            <div class="flex flex-wrap gap-10">
                <?php if (! empty($primary_link['url'])) : ?>
                    <a class="link-underline text-brand-white"
                       href="<?php echo esc_url($primary_link['url']); ?>"
                       <?php echo ! empty($primary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($primary_link['title']); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($secondary_link['url'])) : ?>
                    <a class="font-display text-body text-brand-cream py-3"
                       href="<?php echo esc_url($secondary_link['url']); ?>"
                       <?php echo ! empty($secondary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($secondary_link['title']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
