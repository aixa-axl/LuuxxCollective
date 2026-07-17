<?php
/**
 * Layout: contact-options — Figma 76:5686
 */

$heading    = luux_sub_field('heading');
$subheading = luux_sub_field('subheading');
$section_id = luux_sub_field('section_id');

// WhatsApp column
$wa_title = luux_sub_field('whatsapp_title');
$wa_text  = luux_sub_field('whatsapp_text');
$wa_link  = get_sub_field('whatsapp_link');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_contact_options_whatsapp_link_from_meta')
) {
    $wa_from_meta = luux_contact_options_whatsapp_link_from_meta((int) $post_id, $row_index);

    if ($wa_from_meta !== null) {
        $wa_link = $wa_from_meta;
    }
}

if (is_array($wa_link) && ! empty($wa_link['title'])) {
    $wa_link['title'] = str_replace(['\\u2192', 'u2192'], '→', (string) $wa_link['title']);
}

// Call column
$call_title = luux_sub_field('call_title');
$call_text  = luux_sub_field('call_text');
$call_phone = luux_sub_field('call_phone');
$call_label = luux_sub_field('call_button_label') ?: __('Call Now', 'luux');

// Paper Trail column
$form_title = luux_sub_field('form_title');
$form_text  = luux_sub_field('form_text');
$form_id    = luux_sub_field('form_id');
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="contact-options section-pad bg-brand-white">
    <div class="container-site flex flex-col gap-12 lg:gap-16">
        <?php if ($heading || $subheading) : ?>
            <div class="flex flex-col items-center gap-3 text-center lg:gap-4">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-ink lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($subheading) : ?>
                    <p class="max-w-[36rem] font-body text-body text-brand-primary-muted"><?php echo esc_html($subheading); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="contact-options__columns">
            <?php if ($wa_title || $wa_text || ! empty($wa_link['url'])) : ?>
                <div class="contact-card">
                    <div class="contact-card__body">
                        <?php if ($wa_title) : ?>
                            <h3 class="font-display text-h3 text-brand-ink"><?php echo esc_html($wa_title); ?></h3>
                        <?php endif; ?>
                        <?php if ($wa_text) : ?>
                            <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($wa_text); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (! empty($wa_link['url'])) : ?>
                        <a class="contact-card__button contact-card__button--whatsapp"
                           href="<?php echo esc_url($wa_link['url']); ?>"
                           <?php echo ! empty($wa_link['target']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                            <?php echo esc_html($wa_link['title'] ?: __('Open WhatsApp', 'luux')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($call_title || $call_text || $call_phone) : ?>
                <div class="contact-card">
                    <div class="contact-card__body">
                        <?php if ($call_title) : ?>
                            <h3 class="font-display text-h3 text-brand-ink"><?php echo esc_html($call_title); ?></h3>
                        <?php endif; ?>
                        <?php if ($call_text) : ?>
                            <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($call_text); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($call_phone) : ?>
                        <a class="contact-card__button contact-card__button--call"
                           href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $call_phone)); ?>">
                            <?php echo esc_html($call_label); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($form_title || $form_text || $form_id) : ?>
                <div class="contact-card">
                    <div class="contact-card__body">
                        <?php if ($form_title) : ?>
                            <h3 class="font-display text-h3 text-brand-ink"><?php echo esc_html($form_title); ?></h3>
                        <?php endif; ?>
                        <?php if ($form_text) : ?>
                            <p class="font-body text-body text-brand-primary-muted"><?php echo esc_html($form_text); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($form_id) : ?>
                        <div class="contact-card__form">
                            <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($form_id) . '"]'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
