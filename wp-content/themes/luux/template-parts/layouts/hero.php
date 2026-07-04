<?php
/**
 * Layout: hero
 * ─────────────────────────────────────────────────────────────
 * THE REFERENCE LAYOUT. Every other layout follows these conventions:
 *
 *  1. One file per layout in template-parts/layouts/, hyphenated filename
 *     matching the ACF layout name (underscored): hero → hero.php,
 *     image_text_split → image-text-split.php
 *  2. Read fields with get_sub_field() at the top, escape at output
 *     (esc_html / esc_url / wp_kses_post for rich text).
 *  3. Images are attachment IDs, rendered via wp_get_attachment_image()
 *     with an explicit size — never raw URLs, never unsized <img>.
 *  4. Tailwind utilities only; colours/fonts via brand tokens
 *     (bg-brand-dark, font-display) — no arbitrary hex values.
 *  5. Optional fields guard their own markup — a missing subheading
 *     shouldn't leave an empty tag behind.
 *  6. Section spacing lives on the section, not the page.
 */

$heading    = get_sub_field('heading');
$subheading = get_sub_field('subheading');
$image_id   = get_sub_field('background_image');
$cta        = get_sub_field('cta'); // link field: array{url, title, target}
?>

<section class="relative flex min-h-[90vh] items-end">
    <?php if ($image_id) : ?>
        <?php echo wp_get_attachment_image($image_id, 'full', false, [
            'class'         => 'absolute inset-0 h-full w-full object-cover',
            'fetchpriority' => 'high', // hero image: never lazy-load
        ]); ?>
        <div class="absolute inset-0 bg-black/25" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container-site relative pb-20 text-white">
        <?php if ($heading) : ?>
            <h1 class="font-display max-w-3xl text-5xl leading-tight lg:text-7xl">
                <?php echo esc_html($heading); ?>
            </h1>
        <?php endif; ?>

        <?php if ($subheading) : ?>
            <p class="mt-4 max-w-xl text-lg opacity-90"><?php echo esc_html($subheading); ?></p>
        <?php endif; ?>

        <?php if ($cta) : ?>
            <a class="btn btn-gold mt-8"
               href="<?php echo esc_url($cta['url']); ?>"
               <?php echo $cta['target'] ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo esc_html($cta['title']); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
