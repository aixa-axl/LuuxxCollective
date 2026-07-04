<?php
/**
 * Layout: destinations
 */

$label        = get_sub_field('section_label');
$heading      = get_sub_field('heading');
$destinations = get_sub_field('destinations');
?>

<section class="section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($label || $heading) : ?>
            <div class="flex flex-col items-center gap-4 text-center">
                <?php if ($label) : ?>
                    <p class="font-display text-body uppercase text-brand-gold"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h2 text-brand-primary"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($destinations) : ?>
            <div class="grid grid-cols-2 gap-6 lg:grid-cols-5 lg:gap-8">
                <?php foreach ($destinations as $destination) : ?>
                    <?php
                    $link = $destination['link'] ?? null;
                    $tag  = ! empty($link['url']) ? 'a' : 'article';
                    $attrs = ! empty($link['url'])
                        ? ' href="' . esc_url($link['url']) . '"' . (! empty($link['target']) ? ' target="_blank" rel="noopener"' : '')
                        : '';
                    ?>
                    <<?php echo $tag; ?> class="group flex flex-col items-center gap-4"<?php echo $attrs; ?>>
                        <?php if (! empty($destination['image'])) : ?>
                            <div class="relative aspect-square w-full overflow-hidden rounded bg-brand-cream-light">
                                <?php echo wp_get_attachment_image($destination['image'], 'medium_large', false, [
                                    'class'   => 'h-full w-full object-cover transition-transform duration-300 group-hover:scale-105',
                                    'loading' => 'lazy',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($destination['title'])) : ?>
                            <p class="font-body text-body-lg text-brand-primary"><?php echo esc_html($destination['title']); ?></p>
                        <?php endif; ?>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
