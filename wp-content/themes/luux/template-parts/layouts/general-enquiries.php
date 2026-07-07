<?php
/**
 * Layout: general-enquiries — Figma 76:5716
 */

$heading    = get_sub_field('heading');
$text       = get_sub_field('text');
$email      = get_sub_field('email');
$section_id = get_sub_field('section_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="general-enquiries section-pad bg-brand-cream-light">
    <div class="container-site flex flex-col items-center gap-4 text-center">
        <?php if ($heading) : ?>
            <h2 class="font-display text-h3 text-brand-dark lg:text-h2"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>
        <?php if ($text) : ?>
            <p class="max-w-[32.5rem] font-body text-body text-brand-gold-muted"><?php echo esc_html($text); ?></p>
        <?php endif; ?>
        <?php if ($email) : ?>
            <div class="pt-4">
                <a class="font-body text-body-sm text-brand-dark underline decoration-solid underline-offset-2 transition-opacity hover:opacity-70 focus-visible:opacity-70"
                   href="mailto:<?php echo esc_attr($email); ?>">
                    <?php echo esc_html($email); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
