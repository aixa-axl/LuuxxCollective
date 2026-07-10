<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$on_hero    = luux_uses_hero_header();
$logo_light = luux_get_site_option('site_logo');
$logo_dark  = luux_get_site_option('site_logo_dark');
$enquire    = luux_get_site_option('enquire_link');

$header_class = 'site-header' . ($on_hero ? ' site-header--hero' : '');
?>

<header class="<?php echo esc_attr($header_class); ?>" data-mobile-nav>
    <div class="site-header__inner container-site">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-header__logo">
            <?php if ($logo_light) : ?>
                <?php echo wp_get_attachment_image($logo_light, 'medium', false, [
                    'class' => 'site-header__logo-mark site-header__logo-mark--light',
                ]); ?>
            <?php endif; ?>
            <?php if ($logo_dark) : ?>
                <?php echo wp_get_attachment_image($logo_dark, 'medium', false, [
                    'class' => 'site-header__logo-mark site-header__logo-mark--dark',
                ]); ?>
            <?php endif; ?>
            <?php if (! $logo_light && ! $logo_dark) : ?>
                <span class="site-header__logo-text font-display"><?php bloginfo('name'); ?></span>
            <?php elseif ($logo_light && ! $logo_dark) : ?>
                <span class="site-header__logo-text site-header__logo-fallback font-display"><?php bloginfo('name'); ?></span>
            <?php endif; ?>
        </a>

        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'container_class'=> 'site-header__nav',
            'menu_class'     => 'site-header__menu',
            'fallback_cb'    => false,
        ]);
        ?>

        <div class="site-header__actions">
            <?php if ($enquire) : ?>
                <a class="btn-enquire <?php echo $on_hero ? 'btn-enquire-hero' : ''; ?>"
                   href="<?php echo esc_url($enquire['url']); ?>"
                   <?php echo ! empty($enquire['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <span class="site-header__enquire-short"><?php esc_html_e('Enquire', 'luux'); ?></span>
                    <span class="site-header__enquire-full"><?php echo esc_html($enquire['title'] ?: __('Enquire Now', 'luux')); ?></span>
                </a>
            <?php endif; ?>

            <button type="button"
                    class="site-header__menu-toggle"
                    data-mobile-nav-toggle
                    aria-expanded="false"
                    aria-controls="mobile-nav-panel">
                <span class="sr-only"><?php esc_html_e('Open menu', 'luux'); ?></span>
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-width="1.5" d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>
        </div>
    </div>

    <div id="mobile-nav-panel"
         class="border-t border-brand-cream bg-brand-white px-5 py-6 text-brand-primary lg:hidden"
         data-mobile-nav-panel
         hidden>
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'menu_class'     => 'flex flex-col gap-4 font-body text-body-lg',
            'fallback_cb'    => false,
        ]);
        ?>
    </div>
</header>

<main id="main">
