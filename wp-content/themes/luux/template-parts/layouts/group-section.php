<?php
/**
 * Layout: group-section
 */

$heading       = luux_sub_field('heading');
$heading_lead  = luux_sub_field('heading_lead');
$heading_logo  = luux_sub_field('heading_logo');
$heading_trail = luux_sub_field('heading_trail');
$text          = luux_sub_field('text');
$logos         = luux_sub_field_repeater('logos', [
    ['name' => 'image', 'key' => 'field_luux_group_section_logo_image', 'type' => 'image'],
]);

$has_heading = $heading_logo || $heading;
?>

<section class="bg-brand-dark py-10 lg:section-pad">
    <div class="container-site group-section">
        <?php if ($has_heading || $text) : ?>
            <div class="group-section__copy">
                <?php if ($heading_logo) : ?>
                    <h2 class="group-section__heading font-display text-h3">
                        <?php if ($heading_lead) : ?>
                            <span><?php echo esc_html($heading_lead); ?></span>
                        <?php endif; ?>
                        <span class="group-section__heading-logo">
                            <?php echo wp_get_attachment_image($heading_logo, 'medium', false, [
                                'class'   => 'group-section__heading-logo-image',
                                'loading' => 'lazy',
                            ]); ?>
                        </span>
                        <?php if ($heading_trail) : ?>
                            <span><?php echo esc_html($heading_trail); ?></span>
                        <?php endif; ?>
                    </h2>
                <?php elseif ($heading) : ?>
                    <h2 class="font-display text-h3"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>

                <?php if ($text) : ?>
                    <p class="font-body text-body"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($logos) : ?>
            <div class="group-section__logos">
                <?php foreach ($logos as $logo) :
                    if (empty($logo['image'])) continue;
                    ?>
                    <div class="group-section__logo">
                        <?php echo wp_get_attachment_image($logo['image'], 'medium', false, [
                            'class'   => 'group-section__logo-image',
                            'loading' => 'lazy',
                        ]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
