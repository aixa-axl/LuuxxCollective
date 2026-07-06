<?php
/**
 * Layout: dining — Figma 76:3353
 */

$section_label     = get_sub_field('section_label');
$heading           = get_sub_field('heading');
$text              = get_sub_field('text');
$highlights        = get_sub_field('highlights');
$hero_media_type   = get_sub_field('hero_media_type') ?: 'image';
$image_hero        = get_sub_field('image_hero');
$hero_video_id     = get_sub_field('hero_video');
$image_left        = get_sub_field('image_left');
$image_top         = get_sub_field('image_top');
$image_bottom      = get_sub_field('image_bottom');
$section_id        = get_sub_field('section_id');

$has_hero = ($hero_media_type === 'video' && $hero_video_id) || ($hero_media_type !== 'video' && $image_hero);
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="dining section-pad bg-brand-cream-light">
    <div class="container-site dining__layout">
        <div class="dining__intro">
            <?php if ($section_label) : ?>
                <p class="font-body text-body uppercase text-brand-gold"><?php echo esc_html($section_label); ?></p>
            <?php endif; ?>
            <?php if ($heading) : ?>
                <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="dining__intro-text font-body text-body-lg text-brand-gold-muted"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
            <?php if ($highlights) : ?>
                <ul class="dining__highlights">
                    <?php foreach ($highlights as $item) :
                        if (empty($item['text'])) continue;
                        ?>
                        <li class="font-body text-body text-brand-dark"><?php echo esc_html($item['text']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($has_hero) : ?>
            <div class="dining__hero<?php echo ($hero_media_type === 'video' && $hero_video_id) ? ' dining__hero--video' : ''; ?>">
                <?php if ($hero_media_type === 'video' && $hero_video_id) :
                    $hero_video_url  = wp_get_attachment_url($hero_video_id);
                    $hero_video_mime = get_post_mime_type($hero_video_id);
                    ?>
                    <video class="dining__video"
                           autoplay
                           muted
                           loop
                           playsinline>
                        <?php if ($hero_video_url) : ?>
                            <source src="<?php echo esc_url($hero_video_url); ?>"<?php echo $hero_video_mime ? ' type="' . esc_attr($hero_video_mime) . '"' : ''; ?>>
                        <?php endif; ?>
                    </video>
                <?php elseif ($image_hero) : ?>
                    <?php echo wp_get_attachment_image($image_hero, 'large', false, [
                        'class'   => 'dining__hero-image',
                        'loading' => 'lazy',
                    ]); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($image_left) : ?>
            <div class="dining__image dining__image--tall">
                <?php echo wp_get_attachment_image($image_left, 'large', false, [
                    'class'   => 'dining__media',
                    'loading' => 'lazy',
                ]); ?>
            </div>
        <?php endif; ?>

        <?php if ($image_top) : ?>
            <div class="dining__image dining__image--mid">
                <?php echo wp_get_attachment_image($image_top, 'large', false, [
                    'class'   => 'dining__media',
                    'loading' => 'lazy',
                ]); ?>
            </div>
        <?php endif; ?>

        <?php if ($image_bottom) : ?>
            <div class="dining__image dining__image--short">
                <?php echo wp_get_attachment_image($image_bottom, 'large', false, [
                    'class'   => 'dining__media',
                    'loading' => 'lazy',
                ]); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
