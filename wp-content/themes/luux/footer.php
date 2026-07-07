<?php
/**
 * Site footer — global content from ACF Options.
 */

defined('ABSPATH') || exit;

$tagline      = function_exists('get_field') ? get_field('footer_tagline', 'option') : '';
$logo_id      = function_exists('get_field') ? get_field('site_logo_light', 'option') : null;
$email        = function_exists('get_field') ? get_field('contact_email', 'option') : '';
$phone        = function_exists('get_field') ? get_field('contact_phone', 'option') : '';
$contact_text = function_exists('get_field') ? get_field('contact_intro', 'option') : '';
$group_text   = function_exists('get_field') ? get_field('footer_group_text', 'option') : '';
$socials      = function_exists('get_field') ? get_field('social_links', 'option') : [];
$legal_links  = function_exists('get_field') ? get_field('legal_links', 'option') : [];
$footer_bg    = function_exists('luux_uses_blue_footer') && luux_uses_blue_footer() ? 'bg-brand-primary' : 'bg-brand-navy';
?>

</main>

<footer class="<?php echo esc_attr($footer_bg); ?> text-brand-white">
    <div class="container-site pt-16 pb-10 lg:section-pad lg:pt-20 lg:pb-10">
        <div class="flex flex-col gap-12 lg:grid lg:grid-cols-4 lg:gap-20">
            <div class="flex flex-col gap-6 lg:gap-8">
                <div class="flex flex-col gap-3">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="block h-[1.875rem] w-[11.25rem] lg:h-10 lg:w-[15.375rem]">
                        <?php if ($logo_id) : ?>
                            <?php echo wp_get_attachment_image($logo_id, 'medium', false, [
                                'class' => 'h-full w-full object-contain object-left',
                            ]); ?>
                        <?php else : ?>
                            <span class="font-display text-h3"><?php bloginfo('name'); ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($tagline) : ?>
                        <p class="font-body text-body opacity-70 lg:opacity-80"><?php echo esc_html($tagline); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($socials) : ?>
                    <ul class="flex gap-3">
                        <?php foreach ($socials as $social) :
                            if (empty($social['url'])) continue;
                            ?>
                            <li>
                                <a href="<?php echo esc_url($social['url']); ?>"
                                   class="flex size-8 items-center justify-center rounded-full border border-brand-white opacity-60 transition-opacity hover:opacity-100 focus-visible:opacity-100"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <span class="sr-only"><?php echo esc_html($social['label'] ?? ''); ?></span>
                                    <?php if (! empty($social['icon'])) : ?>
                                        <?php echo wp_get_attachment_image($social['icon'], 'thumbnail', false, [
                                            'class' => 'size-4',
                                        ]); ?>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p class="hidden font-body text-caption font-black opacity-50 lg:block">
                    &copy; <?php echo esc_html(date('Y')); ?> Luxx Collective. <?php esc_html_e('All rights reserved.', 'luux'); ?>
                </p>
            </div>

            <div class="hidden lg:block">
                <p class="mb-6 font-display text-body uppercase"><?php esc_html_e('Explore by travel style', 'luux'); ?></p>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer_travel',
                    'container'      => false,
                    'menu_class'     => 'flex flex-col gap-3 font-body text-body opacity-70',
                    'fallback_cb'    => false,
                ]);
                ?>
            </div>

            <div>
                <p class="mb-4 font-display text-body uppercase lg:mb-6"><?php esc_html_e('Featured destinations', 'luux'); ?></p>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer_destinations',
                    'container'      => false,
                    'menu_class'     => 'flex flex-col gap-3 font-body text-body opacity-60 lg:opacity-70',
                    'fallback_cb'    => false,
                ]);
                ?>
            </div>

            <div class="hidden lg:block">
                <p class="mb-6 font-display text-body uppercase"><?php esc_html_e('Get in touch', 'luux'); ?></p>
                <div class="flex flex-col gap-4 font-body text-body">
                    <?php if ($contact_text) : ?>
                        <p class="opacity-80"><?php echo esc_html($contact_text); ?></p>
                    <?php endif; ?>
                    <?php if ($email) : ?>
                        <a class="hover:opacity-80" href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                    <?php endif; ?>
                    <?php if ($phone) : ?>
                        <a class="hover:opacity-80" href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-brand-white/10">
        <div class="container-site flex flex-col gap-6 py-10 lg:flex-row lg:items-center lg:justify-between lg:gap-4">
            <p class="font-body text-caption font-black opacity-40 lg:hidden">
                &copy; <?php echo esc_html(date('Y')); ?> Luxx Collective. <?php esc_html_e('All rights reserved.', 'luux'); ?>
            </p>

            <?php if ($group_text) : ?>
                <p class="hidden font-body text-body opacity-40 lg:block"><?php echo esc_html($group_text); ?></p>
            <?php endif; ?>

            <?php if ($legal_links) : ?>
                <ul class="flex flex-wrap gap-4 font-body text-body opacity-40 lg:items-center lg:gap-6">
                    <?php foreach ($legal_links as $i => $link) :
                        if (empty($link['link'])) continue;
                        $item = $link['link'];
                        ?>
                        <li class="flex items-center gap-4 lg:gap-6">
                            <?php if ($i > 0) : ?>
                                <span class="hidden size-0.5 rounded-full bg-brand-white/40 lg:inline-block" aria-hidden="true"></span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($item['url']); ?>"
                               <?php echo ! empty($item['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                                <?php echo esc_html($item['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
