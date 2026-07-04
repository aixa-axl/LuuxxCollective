<?php
/**
 * Layout: contact-strip
 */

$caption       = get_sub_field('caption');
$text          = get_sub_field('text');
$primary_link  = get_sub_field('primary_link');
$secondary_link = get_sub_field('secondary_link');
?>

<section class="bg-brand-navy section-pad">
    <div class="container-site flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex max-w-xl flex-col gap-3 text-brand-white">
            <?php if ($caption) : ?>
                <p class="font-body text-caption"><?php echo esc_html($caption); ?></p>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="font-body text-body opacity-80"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($primary_link || $secondary_link) : ?>
            <div class="flex flex-wrap gap-4">
                <?php if (! empty($primary_link['url'])) : ?>
                    <a class="btn btn-filled"
                       href="<?php echo esc_url($primary_link['url']); ?>"
                       <?php echo ! empty($primary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($primary_link['title']); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($secondary_link['url'])) : ?>
                    <a class="btn btn-outline"
                       href="<?php echo esc_url($secondary_link['url']); ?>"
                       <?php echo ! empty($secondary_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($secondary_link['title']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
