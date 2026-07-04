</main>

<footer class="bg-brand-navy text-brand-cream">
    <div class="container-site py-16">
        <!-- TODO: build from Figma footer frame via MCP.
             Contact details, nav columns, socials → ACF Options Page (site_options)
             so the client edits them once, not per page. -->
        <div class="flex flex-col gap-10 lg:flex-row lg:justify-between">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="font-display text-2xl">
                <?php bloginfo('name'); ?>
            </a>
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'container'      => false,
                'menu_class'     => 'flex flex-wrap gap-6 text-sm',
                'fallback_cb'    => false,
            ]);
            ?>
        </div>
        <p class="mt-12 text-xs opacity-60">&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
