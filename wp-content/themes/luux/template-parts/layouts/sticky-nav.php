<?php
/**
 * Layout: sticky-nav
 * Fixed in-page anchor bar (Sani / Ikos resort pages).
 */

$nav_links        = get_sub_field('nav_links');
$enquire_link     = get_sub_field('enquire_link');
$specialist_link  = get_sub_field('specialist_link');
?>

<nav class="sticky-nav" data-sticky-nav aria-label="<?php esc_attr_e('Page sections', 'luux'); ?>">
    <div class="sticky-nav__inner container-site">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="sticky-nav__brand font-display text-h3 text-brand-primary lg:hidden">
            <?php bloginfo('name'); ?>
        </a>

        <?php if ($nav_links) : ?>
            <ul class="sticky-nav__links">
                <?php foreach ($nav_links as $row) :
                    $link = $row['link'] ?? null;
                    if (empty($link['url'])) continue;
                    ?>
                    <li>
                        <a href="<?php echo esc_url($link['url']); ?>"
                           <?php echo ! empty($link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                            <?php echo esc_html($link['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="sticky-nav__actions">
            <?php if (! empty($enquire_link['url'])) : ?>
                <a class="btn-enquire"
                   href="<?php echo esc_url($enquire_link['url']); ?>"
                   <?php echo ! empty($enquire_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($enquire_link['title']); ?>
                </a>
            <?php endif; ?>
            <?php if (! empty($specialist_link['url'])) : ?>
                <a class="btn-enquire sticky-nav__specialist"
                   href="<?php echo esc_url($specialist_link['url']); ?>"
                   <?php echo ! empty($specialist_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo esc_html($specialist_link['title']); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>
