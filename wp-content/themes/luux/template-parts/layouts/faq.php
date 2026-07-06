<?php
/**
 * Layout: faq
 */

$heading    = get_sub_field('heading');
$items      = get_sub_field('items');
$section_id = get_sub_field('section_id');

if (! $items) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="faq section-pad" data-faq>
    <div class="container-site flex flex-col gap-10 lg:gap-16">
        <?php if ($heading) : ?>
            <h2 class="text-center font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
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
