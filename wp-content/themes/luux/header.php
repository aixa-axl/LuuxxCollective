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
$on_hero   = is_front_page();
$logo_id   = function_exists('get_field') ? get_field('site_logo', 'option') : null;
$enquire   = function_exists('get_field') ? get_field('enquire_link', 'option') : null;

$header_class = implode(' ', array_filter([
    'fixed inset-x-0 top-0 z-50 h-16 border-b',
    'bg-white border-brand-cream text-brand-primary',
    $on_hero ? 'lg:absolute lg:h-auto lg:border-transparent lg:bg-transparent lg:text-brand-white' : '',
]));
?>

<header class="<?php echo esc_attr($header_class); ?>" data-mobile-nav>
    <div class="container-site flex h-full items-center justify-between lg:grid lg:h-auto lg:grid-cols-[1fr_auto_1fr] lg:items-center lg:py-6">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="relative block h-5 w-[7.5rem] shrink-0 lg:h-10 lg:w-[15.375rem] lg:justify-self-start">
            <?php if ($logo_id) : ?>
                <?php echo wp_get_attachment_image($logo_id, 'medium', false, [
                    'class' => 'h-full w-full object-contain object-left',
                ]); ?>
            <?php else : ?>
                <span class="font-display text-h3 lg:text-h2"><?php bloginfo('name'); ?></span>
            <?php endif; ?>
        </a>

        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'container_class'=> 'hidden lg:block lg:justify-self-center',
            'menu_class'     => 'flex gap-8 font-body text-body ' . ($on_hero ? 'text-brand-white' : 'text-brand-primary'),
            'fallback_cb'    => false,
        ]);
        ?>

        <div class="flex items-center gap-4 lg:justify-self-end">
            <?php if ($enquire) : ?>
                <a class="btn-enquire <?php echo $on_hero ? 'btn-enquire-hero' : ''; ?>"
                   href="<?php echo esc_url($enquire['url']); ?>"
                   <?php echo ! empty($enquire['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($enquire['title'] ?: __('Enquire Now', 'luux')); ?>
                </a>
            <?php endif; ?>

            <button type="button"
                    class="flex size-6 items-center justify-center lg:hidden"
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
