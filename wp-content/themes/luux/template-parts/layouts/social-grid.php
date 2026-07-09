<?php
/**
 * Layout: social-grid
 *
 * Live feed from Instagram (Site Options → Instagram).
 */

$heading     = __('Join us on social', 'luux');
$post_count  = (int) get_sub_field('post_count');
$posts       = luux_get_instagram_posts($post_count > 0 ? $post_count : null);
$profile_url = luux_instagram_profile_url();
?>

<section class="social-grid section-pad" data-social-grid>
    <div class="container-site social-grid__inner">
        <h2 class="social-grid__heading font-display text-h3 leading-[1.1] text-brand-dark lg:text-h2 lg:leading-none">
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

        <?php if ($posts) : ?>
            <div class="social-grid__grid">
                <?php foreach ($posts as $post) :
                    $alt = $post['caption'] !== ''
                        ? wp_trim_words($post['caption'], 12, '…')
                        : sprintf(
                            /* translators: %s: Instagram username */
                            __('Instagram post by %s', 'luux'),
                            luux_instagram_username()
                        );
                    ?>
                    <button type="button"
                            class="social-grid__item group"
                            data-social-grid-open
                            data-permalink="<?php echo esc_url($post['permalink']); ?>"
                            aria-label="<?php echo esc_attr(sprintf(
                                /* translators: %s: Instagram post alt text */
                                __('View Instagram post: %s', 'luux'),
                                $alt
                            )); ?>">
                        <img class="social-grid__image transition-transform duration-300 group-hover:scale-105"
                             src="<?php echo esc_url($post['image_url']); ?>"
                             alt="<?php echo esc_attr($alt); ?>"
                             loading="lazy"
                             decoding="async"
                             width="315"
                             height="400">
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="social-grid-modal" data-social-grid-modal hidden>
                <div class="social-grid-modal__backdrop" data-social-grid-close tabindex="-1" aria-hidden="true"></div>
                <div class="social-grid-modal__dialog"
                     role="dialog"
                     aria-modal="true"
                     aria-label="<?php esc_attr_e('Instagram post', 'luux'); ?>">
                    <button type="button"
                            class="social-grid-modal__close"
                            data-social-grid-close
                            aria-label="<?php esc_attr_e('Close', 'luux'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <div class="social-grid-modal__embed" data-social-grid-embed></div>
                </div>
            </div>
        <?php elseif (current_user_can('edit_posts')) : ?>
            <p class="font-body text-body text-brand-primary-muted">
                <?php esc_html_e('Instagram feed not configured. Add your API credentials under Site Options → Instagram.', 'luux'); ?>
            </p>
        <?php endif; ?>
    </div>
</section>
