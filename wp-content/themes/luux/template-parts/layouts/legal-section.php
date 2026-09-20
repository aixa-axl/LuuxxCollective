<?php
/**
 * Layout: legal-section — numbered legal block (e.g. SECTION A) with clause repeater.
 */

$heading    = luux_sub_field('heading');
$intro      = luux_sub_field('intro');
$clauses    = get_sub_field('clauses');
$section_id = luux_sub_field('section_id');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_page_sections_uses_legacy_storage')
    && luux_page_sections_uses_legacy_storage($post_id)
    && function_exists('luux_legal_section_clauses_from_meta')
) {
    $from_meta = luux_legal_section_clauses_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $clauses = $from_meta;
    }
}

if (! $heading && ! $intro && empty($clauses)) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="legal-section section-pad bg-brand-white">
    <div class="container-site flex flex-col gap-10 lg:gap-12">
        <?php if ($heading || $intro) : ?>
            <div class="flex max-w-3xl flex-col gap-4 lg:gap-6">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($intro) : ?>
                    <div class="legal-content">
                        <?php echo wp_kses_post($intro); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($clauses) : ?>
            <div class="flex max-w-3xl flex-col gap-10 lg:gap-12">
                <?php foreach ($clauses as $clause) :
                    $title = isset($clause['title']) ? (string) $clause['title'] : '';
                    $body  = isset($clause['body']) ? (string) $clause['body'] : '';

                    if ($title === '' && $body === '') {
                        continue;
                    }
                    ?>
                    <article class="flex flex-col gap-3 lg:gap-4">
                        <?php if ($title !== '') : ?>
                            <h3 class="font-display text-quote text-brand-primary"><?php echo esc_html($title); ?></h3>
                        <?php endif; ?>
                        <?php if ($body !== '') : ?>
                            <div class="legal-content">
                                <?php echo wp_kses_post($body); ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
