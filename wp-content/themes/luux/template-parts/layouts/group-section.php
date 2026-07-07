<?php
/**
 * Layout: group-section
 */

$heading = get_sub_field('heading');
$text    = get_sub_field('text');
$logos   = get_sub_field('logos');
?>

<section class="bg-brand-dark p-10 lg:section-pad">
    <div class="container-site flex flex-col gap-8 lg:flex-row lg:items-center lg:gap-20">
        <?php if ($heading || $text) : ?>
            <div class="flex flex-1 flex-col gap-4 text-brand-white">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="font-body text-body"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($logos) : ?>
            <div class="grid grid-cols-2 gap-4 lg:flex lg:flex-wrap lg:gap-8">
                <?php foreach ($logos as $logo) :
                    if (empty($logo['image'])) continue;
                    ?>
                    <div class="flex h-10 w-full items-center justify-center bg-brand-white/10 p-2 lg:w-32">
                        <?php echo wp_get_attachment_image($logo['image'], 'medium', false, [
                            'class'   => 'max-h-full max-w-full object-contain',
                            'loading' => 'lazy',
                        ]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
