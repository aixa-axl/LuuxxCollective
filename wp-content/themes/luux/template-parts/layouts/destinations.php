<?php
/**
 * Layout: destinations
 */

$label        = luux_sub_field('section_label');
$heading      = luux_sub_field('heading');
$destinations = get_sub_field('destinations');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_destinations_items_from_meta')
) {
    $from_meta = luux_destinations_items_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $destinations = $from_meta;
    }
}
?>

<section class="section-pad">
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($label || $heading) : ?>
            <div class="section-heading text-left lg:items-center lg:text-center">
                <?php if ($label) : ?>
                    <p class="font-ui font-medium text-body uppercase text-brand-gold"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 leading-[1.1] text-brand-primary lg:text-h2 lg:leading-none"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($destinations) : ?>
            <div class="flex flex-col gap-4 md:grid md:grid-cols-2 md:gap-8 lg:grid-cols-3 xl:grid-cols-5">
                <?php foreach ($destinations as $destination) :
                    $link = $destination['link'] ?? null;
                    $tag  = ! empty($link['url']) ? 'a' : 'article';
                    $attrs = ! empty($link['url'])
                        ? ' href="' . esc_url($link['url']) . '"' . (! empty($link['target']) ? ' target="_blank" rel="noopener"' : '')
                        : '';
                    ?>
                    <<?php echo $tag; ?>
                        class="group flex items-center gap-4 rounded border border-brand-cream-light p-3 md:flex-col md:items-center md:gap-4 md:border-0 md:p-0"
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
                            <p class="flex-1 font-body text-body-lg text-brand-primary md:flex-none md:text-center"><?php echo esc_html($destination['title']); ?></p>
                        <?php endif; ?>
                        <svg class="size-5 shrink-0 text-brand-gold md:hidden" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M7.5 5l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
