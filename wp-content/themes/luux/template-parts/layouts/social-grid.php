<?php
/**
 * Layout: social-grid
 *
 * Live feed from Instagram (Site Options → Instagram).
 */

$heading     = get_sub_field('heading');
$post_count  = (int) get_sub_field('post_count');
$posts       = luux_get_instagram_posts($post_count > 0 ? $post_count : null);
$profile_url = luux_instagram_profile_url();
?>

<section class="section-pad">
    <div class="container-site flex flex-col gap-10">
        <?php if ($heading) : ?>
            <h2 class="font-display text-h3 leading-[1.1] text-brand-dark lg:text-h2 lg:leading-none">
                <?php if ($posts) : ?>
                    <a class="transition-opacity hover:opacity-75 focus-visible:opacity-75"
                       href="<?php echo esc_url($profile_url); ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <?php echo esc_html($heading); ?>
                    </a>
                <?php else : ?>
                    <?php echo esc_html($heading); ?>
                <?php endif; ?>
            </h2>
        <?php endif; ?>

        <?php if ($posts) : ?>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4 lg:gap-4">
                <?php foreach ($posts as $post) :
                    $alt = $post['caption'] !== ''
                        ? wp_trim_words($post['caption'], 12, '…')
                        : sprintf(
                            /* translators: %s: Instagram username */
                            __('Instagram post by %s', 'luux'),
                            luux_instagram_username()
                        );
                    ?>
                    <a class="group relative block aspect-square overflow-hidden bg-brand-cream-light"
                       href="<?php echo esc_url($post['permalink']); ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <img class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                             src="<?php echo esc_url($post['image_url']); ?>"
                             alt="<?php echo esc_attr($alt); ?>"
                             loading="lazy"
                             decoding="async"
                             width="400"
                             height="400">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif (current_user_can('edit_posts')) : ?>
            <p class="font-body text-body text-brand-primary-muted">
                <?php esc_html_e('Instagram feed not configured. Add your API credentials under Site Options → Instagram.', 'luux'); ?>
            </p>
        <?php endif; ?>
    </div>
</section>
