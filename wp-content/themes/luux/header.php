<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="absolute inset-x-0 top-0 z-50">
    <div class="container-site flex items-center justify-between py-6">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="font-display text-2xl text-white">
            <?php bloginfo('name'); ?>
        </a>

        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'container_class'=> 'hidden lg:block',
            'menu_class'     => 'flex gap-8 text-sm tracking-widest uppercase text-white',
            'fallback_cb'    => false,
        ]);
        ?>

        <!-- TODO: mobile menu toggle — build with header from Figma MCP -->
    </div>
</header>

<main id="main">
