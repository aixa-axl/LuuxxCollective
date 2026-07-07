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
            <div class="section-heading text-left lg:items-center lg:text-center">
                <?php if ($label) : ?>
                    <p class="font-ui font-semibold text-body uppercase text-brand-gold"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 leading-[1.1] text-brand-primary lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($destinations) : ?>
            <div class="flex flex-col gap-4 lg:grid lg:grid-cols-5 lg:gap-8">
                <?php foreach ($destinations as $destination) :
                    $link = $destination['link'] ?? null;
                    $tag  = ! empty($link['url']) ? 'a' : 'article';
                    $attrs = ! empty($link['url'])
                        ? ' href="' . esc_url($link['url']) . '"' . (! empty($link['target']) ? ' target="_blank" rel="noopener"' : '')
                        : '';
                    ?>
                    <<?php echo $tag; ?>
                        class="group flex items-center gap-4 rounded border border-brand-cream-light p-3 lg:flex-col lg:items-center lg:gap-4 lg:border-0 lg:p-0"
                        <?php echo $attrs; ?>>
                        <?php if (! empty($destination['image'])) : ?>
                            <div class="destination-card__image relative shrink-0 overflow-hidden rounded-sm">
                                <?php echo wp_get_attachment_image($destination['image'], 'medium_large', false, [
                                    'class'   => 'h-full w-full object-cover transition-transform duration-300 lg:group-hover:scale-105',
                                    'loading' => 'lazy',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($destination['title'])) : ?>
                            <p class="flex-1 font-body text-body-lg text-brand-primary lg:flex-none lg:text-center"><?php echo esc_html($destination['title']); ?></p>
                        <?php endif; ?>
                        <svg class="size-5 shrink-0 text-brand-gold lg:hidden" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M7.5 5l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
