<?php
/**
 * Layout: trust-strip
 */

$rating      = get_sub_field('rating');
$press_logos = get_sub_field('press_logos');
$award       = get_sub_field('award');
?>

<section class="trust-strip bg-brand-white">
    <div class="container-site trust-strip__inner">
        <?php if ($rating) : ?>
            <p class="trust-strip__rating font-body text-body text-brand-primary"><?php echo esc_html($rating); ?></p>
        <?php endif; ?>

        <?php if ($press_logos) : ?>
            <div class="trust-strip__divider" aria-hidden="true"></div>
            <ul class="trust-strip__press">
                <?php foreach ($press_logos as $logo) :
                    if (empty($logo['name'])) continue;
                    ?>
                    <li class="font-body text-caption font-black uppercase text-brand-primary"><?php echo esc_html($logo['name']); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($award) : ?>
            <div class="trust-strip__divider" aria-hidden="true"></div>
            <p class="trust-strip__award font-body text-body text-brand-primary"><?php echo esc_html($award); ?></p>
        <?php endif; ?>
    </div>
</section>
