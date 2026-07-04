<?php
/**
 * Layout: contact-strip
 */

$caption        = get_sub_field('caption');
$text           = get_sub_field('text');
$primary_link   = get_sub_field('primary_link');
$secondary_link = get_sub_field('secondary_link');
?>

<section class="bg-brand-navy p-10 lg:section-pad">
    <div class="container-site flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-col gap-2 text-center text-brand-white lg:max-w-xl lg:text-left">
            <?php if ($caption) : ?>
                <p class="font-display text-body-lg lg:font-body lg:text-caption"><?php echo esc_html($caption); ?></p>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="font-body text-body opacity-80"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($primary_link || $secondary_link) : ?>
            <div class="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:gap-4">
                <?php if (! empty($primary_link['url'])) : ?>
                    <a class="btn btn-filled btn-block"
                       href="<?php echo esc_url($primary_link['url']); ?>"
                       <?php echo ! empty($primary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($primary_link['title']); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($secondary_link['url'])) : ?>
                    <a class="btn btn-outline btn-block"
                       href="<?php echo esc_url($secondary_link['url']); ?>"
                       <?php echo ! empty($secondary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($secondary_link['title']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
