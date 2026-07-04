<?php
/**
 * Layout: hero
 */

$heading    = get_sub_field('heading');
$subheading = get_sub_field('subheading');
$image_id   = get_sub_field('background_image');
$ctas       = get_sub_field('ctas');
?>

<section class="relative flex h-[700px] items-center justify-center overflow-hidden">
    <?php if ($image_id) : ?>
        <?php echo wp_get_attachment_image($image_id, 'full', false, [
            'class'         => 'absolute inset-0 h-full w-full object-cover',
            'fetchpriority' => 'high',
        ]); ?>
        <div class="absolute inset-0 bg-gradient-to-t from-brand-primary/80 via-brand-primary/50 to-brand-primary/20" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container-site relative z-10 flex max-w-[58.375rem] flex-col items-center gap-8 text-center text-brand-white">
        <?php if ($heading || $subheading) : ?>
            <div class="flex flex-col gap-4">
                <?php if ($heading) : ?>
                    <h1 class="font-display text-h1 leading-[0.88]"><?php echo esc_html($heading); ?></h1>
                <?php endif; ?>
                <?php if ($subheading) : ?>
                    <p class="font-body text-body"><?php echo esc_html($subheading); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($ctas) : ?>
            <div class="flex flex-wrap items-center justify-center gap-10">
                <?php foreach ($ctas as $row) :
                    $link = $row['link'] ?? null;
                    if (empty($link['url'])) continue;
                    ?>
                    <a class="link-underline text-brand-white"
                       href="<?php echo esc_url($link['url']); ?>"
                       <?php echo ! empty($link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html($link['title']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
