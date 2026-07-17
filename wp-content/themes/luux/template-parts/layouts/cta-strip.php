<?php
/**
 * Layout: cta-strip
 */

$text           = luux_sub_field('text');
$primary_link   = luux_sub_field_link('primary_link');
$secondary_link = luux_sub_field_link('secondary_link');
?>

<section class="bg-brand-dark p-10 lg:section-pad">
    <div class="container-site flex flex-col items-center gap-8 text-center lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:text-left">
        <?php if ($text) : ?>
            <p class="font-body text-body-lg text-brand-white lg:max-w-none"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if ($primary_link || $secondary_link) : ?>
            <div class="flex w-full flex-col items-center gap-4 lg:w-auto lg:flex-row lg:gap-10">
                <?php if (! empty($primary_link['url'])) : ?>
                    <a class="link-underline-block link-underline-block--ruled text-brand-white"
                       href="<?php echo esc_url($primary_link['url']); ?>"
                       <?php echo ! empty($primary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($primary_link['title']); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($secondary_link['url'])) : ?>
                    <a class="link-underline-block font-ui text-body text-brand-cream lg:py-3"
                       href="<?php echo esc_url($secondary_link['url']); ?>"
                       <?php echo ! empty($secondary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($secondary_link['title']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
