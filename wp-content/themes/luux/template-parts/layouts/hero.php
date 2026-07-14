<?php
/**
 * Layout: hero
 */

$heading        = get_sub_field('heading');
$subheading     = get_sub_field('subheading');
$media_type     = get_sub_field('media_type') ?: 'image';
$image_id       = get_sub_field('background_image');
$video_id       = get_sub_field('background_video');
$ctas           = get_sub_field('ctas');
$show_group_tag = get_sub_field('show_group_tag');
if ($show_group_tag === null || $show_group_tag === '') {
    $show_group_tag = true;
}
$group_tag_logo = get_sub_field('group_tag_logo');

$has_video = ($media_type === 'video' && $video_id);
$has_media = $has_video || $image_id;
?>

<section class="hero<?php echo is_front_page() ? ' hero--home-bleed' : ''; ?> relative h-[640px] overflow-hidden lg:h-[700px]">
    <?php if ($has_video) :
        $video_url  = wp_get_attachment_url($video_id);
        $video_mime = get_post_mime_type($video_id);
        ?>
        <video class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline>
            <?php if ($video_url) : ?>
                <source src="<?php echo esc_url($video_url); ?>"<?php echo $video_mime ? ' type="' . esc_attr($video_mime) . '"' : ''; ?>>
            <?php endif; ?>
        </video>
    <?php elseif ($image_id) : ?>
        <?php echo wp_get_attachment_image($image_id, 'full', false, [
            'class'         => 'absolute inset-0 h-full w-full object-cover',
            'fetchpriority' => 'high',
        ]); ?>
    <?php endif; ?>
    <?php if ($has_media) : ?>
        <div class="hero-scrim absolute inset-0" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if (is_front_page() && $show_group_tag) : ?>
        <div class="hero__group-tag pointer-events-none absolute inset-x-0 z-10">
            <p class="hero__group-tag-label inline-flex max-w-[calc(100%-1.25rem)] items-center gap-x-1.5 rounded-r-2xl bg-brand-primary py-2.5 pr-5 pl-5 font-body text-body-sm font-light text-brand-white sm:max-w-none md:gap-x-2 md:pr-6 lg:py-3 lg:pr-8 lg:pl-[var(--spacing-gutter)]">
                <span><?php esc_html_e('Part of the', 'luux'); ?></span>
                <?php if ($group_tag_logo) : ?>
                    <?php echo wp_get_attachment_image($group_tag_logo, 'medium', false, [
                        'class'   => 'hero__group-tag-logo',
                        'loading' => 'lazy',
                    ]); ?>
                <?php endif; ?>
                <span><?php esc_html_e('Group', 'luux'); ?></span>
            </p>
        </div>
    <?php endif; ?>

    <div class="absolute inset-x-0 bottom-0 z-10 flex w-full flex-col items-center gap-6 px-5 pb-16 text-center text-brand-white lg:inset-x-auto lg:bottom-auto lg:left-1/2 lg:top-[calc(50%+167px)] lg:w-full lg:max-w-[58.375rem] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:gap-8 lg:px-0 lg:pb-0">
        <?php if ($heading || $subheading) : ?>
            <div class="flex w-full flex-col gap-3 lg:gap-4">
                <?php if ($heading) : ?>
                    <h1 class="font-display text-[3rem] leading-none lg:text-h1 lg:leading-[0.88]"><?php echo esc_html($heading); ?></h1>
                <?php endif; ?>
                <?php if ($subheading) : ?>
                    <p class="font-body text-body"><?php echo esc_html($subheading); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($ctas) : ?>
            <div class="hero__ctas flex w-full flex-col gap-4 lg:w-auto lg:flex-row lg:justify-center lg:gap-10">
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
