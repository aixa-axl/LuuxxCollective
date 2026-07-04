<?php
/**
 * Layout: hero
 */

$heading    = get_sub_field('heading');
$subheading = get_sub_field('subheading');
$image_id   = get_sub_field('background_image');
$ctas       = get_sub_field('ctas');
?>

<section class="relative flex h-[640px] items-end overflow-hidden lg:h-[700px] lg:items-center lg:justify-center">
    <?php if ($image_id) : ?>
        <?php echo wp_get_attachment_image($image_id, 'full', false, [
            'class'         => 'absolute inset-0 h-full w-full object-cover',
            'fetchpriority' => 'high',
        ]); ?>
        <div class="absolute inset-0 bg-gradient-to-r from-brand-primary/80 via-brand-primary/50 to-brand-primary/20 lg:bg-gradient-to-t lg:from-brand-primary/80 lg:via-brand-primary/50 lg:to-brand-primary/20" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container-site relative z-10 flex w-full flex-col items-center gap-6 pb-16 text-center text-brand-white lg:max-w-[58.375rem] lg:gap-8 lg:pb-0">
        <?php if ($heading || $subheading) : ?>
            <div class="flex flex-col gap-3 lg:gap-4">
                <?php if ($heading) : ?>
                    <h1 class="font-display text-[3rem] leading-none lg:text-h1 lg:leading-[0.88]"><?php echo esc_html($heading); ?></h1>
                <?php endif; ?>
                <?php if ($subheading) : ?>
                    <p class="font-body text-body"><?php echo esc_html($subheading); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($ctas) : ?>
            <div class="flex w-full flex-col gap-4 lg:w-auto lg:flex-row lg:flex-wrap lg:justify-center lg:gap-10">
                <?php foreach ($ctas as $i => $row) :
                    $link = $row['link'] ?? null;
                    if (empty($link['url'])) continue;
                    $is_first = $i === 0;
                    ?>
                    <a class="<?php echo $is_first ? 'link-underline-block link-underline-block--ruled' : 'link-underline-block'; ?> text-brand-white"
                       href="<?php echo esc_url($link['url']); ?>"
                       <?php echo ! empty($link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($link['title']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
