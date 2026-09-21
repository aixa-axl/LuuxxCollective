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

// Prevent the same legal section row rendering twice in one request.
static $luux_legal_section_rendered = [];
$render_key = (int) $post_id . ':' . (int) $row_index;

if ($post_id && $row_index >= 0 && isset($luux_legal_section_rendered[$render_key])) {
    return;
}

if ($post_id && $row_index >= 0) {
    $luux_legal_section_rendered[$render_key] = true;
}

// Prefer direct postmeta when present — legal scalars/clauses are custom-saved.
if ($post_id && $row_index >= 0 && function_exists('luux_read_section_meta')) {
    foreach (['heading', 'intro', 'section_id'] as $name) {
        $from_meta = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($from_meta !== null && $from_meta !== '') {
            ${$name} = $from_meta;
        }
    }
}

if (
    $post_id
    && $row_index >= 0
    && function_exists('luux_legal_section_clauses_from_meta')
) {
    $from_meta = luux_legal_section_clauses_from_meta((int) $post_id, $row_index);

    if ($from_meta !== []) {
        $clauses = $from_meta;
    }
}

// Fall back to stash when postmeta is empty (e.g. after a save that wrote layout shells only).
if ($post_id && $row_index >= 0) {
    $stash = get_post_meta((int) $post_id, '_luux_legal_section_stash', true);

    if (is_array($stash) && isset($stash[(string) $row_index]) && is_array($stash[(string) $row_index])) {
        $row_stash = $stash[(string) $row_index];

        foreach (['heading', 'intro', 'section_id'] as $name) {
            if ((${$name} === '' || ${$name} === null || ${$name} === false) && ! empty($row_stash[$name])) {
                ${$name} = $row_stash[$name];
            }
        }

        if (empty($clauses) && ! empty($row_stash['_clauses_json']) && function_exists('luux_acf_legal_section_decode_clauses_json')) {
            $from_stash = luux_acf_legal_section_decode_clauses_json($row_stash['_clauses_json']);

            if ($from_stash !== []) {
                $clauses = $from_stash;
            }
        }
    }
}

if (! $heading && ! $intro && empty($clauses)) {
    return;
}
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="legal-section section-pad bg-brand-white">
    <div class="container-site flex flex-col gap-10 lg:gap-12">
        <?php if ($heading || $intro) : ?>
            <div class="flex w-full flex-col gap-4 lg:gap-6">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary lg:text-h2"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($intro) : ?>
                    <div class="legal-content w-full">
                        <?php echo function_exists('luux_format_legal_html') ? luux_format_legal_html((string) $intro) : wp_kses_post($intro); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($clauses) : ?>
            <div class="flex w-full flex-col gap-10 lg:gap-12">
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
                            <div class="legal-content w-full">
                                <?php echo function_exists('luux_format_legal_html') ? luux_format_legal_html((string) $body) : wp_kses_post($body); ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
