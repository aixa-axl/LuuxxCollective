<?php
/**
 * Layout: faq
 */

$heading    = luux_sub_field('heading');
$section_id = luux_sub_field('section_id');
$items      = get_sub_field('items');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_faq_items_from_meta')
) {
    $from_meta = luux_faq_items_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $items = $from_meta;
    }
}

if (! $items) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="faq section-pad" data-faq>
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($heading) : ?>
            <h2 class="text-left font-display text-h3 text-brand-primary lg:text-center lg:text-h2"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <div class="faq__list mx-auto w-full max-w-3xl">
            <?php foreach ($items as $i => $item) :
                if (empty($item['question'])) continue;
                $answer_id = 'faq-answer-' . get_row_index() . '-' . $i;
                ?>
                <div class="faq__item">
                    <h3>
                        <button type="button"
                                class="faq__trigger"
                                aria-expanded="false"
                                aria-controls="<?php echo esc_attr($answer_id); ?>">
                            <span><?php echo esc_html($item['question']); ?></span>
                            <span class="faq__icon" aria-hidden="true">+</span>
                        </button>
                    </h3>
                    <?php if (! empty($item['answer'])) : ?>
                        <div class="faq__panel" id="<?php echo esc_attr($answer_id); ?>" hidden>
                            <div class="faq__answer font-body text-body text-brand-primary-muted">
                                <?php echo wp_kses_post($item['answer']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
