<?php
/**
 * Layout: hero
 * Homepage: tall marketing hero (Figma home).
 * Inner pages: contact-hero style — centered title over image.
 */

$heading    = luux_sub_field('heading');
$subheading = luux_sub_field('subheading');
$media_type = luux_sub_field('media_type') ?: 'image';
$image_id   = luux_sub_field('background_image');
$video_id   = luux_sub_field('background_video');
$ctas       = get_sub_field('ctas');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

// Prefer direct postmeta / stash — hero scalars are custom-saved and ACF-blocked.
if ($post_id && $row_index >= 0 && function_exists('luux_read_section_meta')) {
    foreach (['heading', 'subheading', 'media_type', 'background_image', 'background_video'] as $name) {
        $from_meta = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($from_meta === null || $from_meta === '') {
            continue;
        }

        if (in_array($name, ['background_image', 'background_video'], true)) {
            ${$name === 'background_image' ? 'image_id' : 'video_id'} = (int) $from_meta;
            continue;
        }

        ${$name} = $from_meta;
    }
}

if ($post_id && $row_index >= 0) {
    $stash = get_post_meta((int) $post_id, '_luux_hero_stash', true);

    if (is_array($stash) && isset($stash[(string) $row_index]) && is_array($stash[(string) $row_index])) {
        $row_stash = $stash[(string) $row_index];

        foreach (['heading', 'subheading', 'media_type'] as $name) {
            if ((${$name} === '' || ${$name} === null || ${$name} === false) && ! empty($row_stash[$name])) {
                ${$name} = $row_stash[$name];
            }
        }

        if (empty($image_id) && ! empty($row_stash['background_image'])) {
            $image_id = (int) $row_stash['background_image'];
        }

        if (empty($video_id) && ! empty($row_stash['background_video'])) {
            $video_id = (int) $row_stash['background_video'];
        }
    }
}

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_hero_ctas_from_meta')
) {
    $from_meta = luux_hero_ctas_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $ctas = $from_meta;
    }
}

$media_type = $media_type ?: 'image';
$has_video  = ($media_type === 'video' && $video_id);
$has_media  = $has_video || $image_id;
$is_home    = is_front_page();

// Inner pages (Terms, etc.) match contact-hero: shorter, vertically centered title.
if (! $is_home) :
    ?>
<section class="hero hero--page relative flex h-[26.25rem] items-center justify-center overflow-hidden lg:h-[30.625rem]<?php echo $has_media ? '' : ' bg-brand-dark'; ?>">
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
        <div class="contact-hero__scrim absolute inset-0" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if ($heading || $subheading || $ctas) : ?>
        <div class="container-site relative z-10 flex flex-col items-center gap-6 text-center text-brand-white lg:gap-8">
            <?php if ($heading || $subheading) : ?>
                <div class="flex max-w-[42.5rem] flex-col gap-3 lg:gap-4">
                    <?php if ($heading) : ?>
                        <h1 class="font-display text-[2.5rem] leading-none lg:text-h1 lg:leading-[0.88]"><?php echo esc_html($heading); ?></h1>
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
                        if (empty($link['url'])) {
                            continue;
                        }
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
    <?php endif; ?>
</section>
    <?php
    return;
endif;
?>

<section class="hero hero--home-bleed relative h-[640px] overflow-hidden lg:h-[700px]<?php echo $has_media ? '' : ' bg-brand-dark'; ?>">
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
                    if (empty($link['url'])) {
                        continue;
                    }
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
