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

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="legal-section bg-brand-white py-6 lg:py-8">
    <div class="container-site flex flex-col gap-5 lg:gap-6">
        <?php if ($heading || $intro) : ?>
            <div class="flex w-full flex-col gap-3 lg:gap-4">
                <?php if ($heading) : ?>
                    <h2 class="font-display text-h3 text-brand-primary"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text((string) $heading) : esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($intro) : ?>
                    <div class="legal-content w-full">
                        <?php echo function_exists('luux_format_legal_html') ? luux_format_legal_html((string) $intro) : wp_kses_post($intro); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($clauses) : ?>
            <div class="flex w-full flex-col gap-6 lg:gap-8">
                <?php foreach ($clauses as $clause) :
                    $title = isset($clause['title']) ? (string) $clause['title'] : '';
                    $body  = isset($clause['body']) ? (string) $clause['body'] : '';

                    $show_table = ! empty($clause['show_table']);
                    $header_1   = isset($clause['table_header_col_1']) ? (string) $clause['table_header_col_1'] : '';
                    $header_2   = isset($clause['table_header_col_2']) ? (string) $clause['table_header_col_2'] : '';
                    $header_2_sub = isset($clause['table_header_col_2_sub']) ? (string) $clause['table_header_col_2_sub'] : '';
                    $table_rows = (isset($clause['table_rows']) && is_array($clause['table_rows'])) ? $clause['table_rows'] : [];
                    $has_table  = $show_table && ($header_1 !== '' || $header_2 !== '' || $table_rows !== []);

                    if ($title === '' && $body === '' && ! $has_table) {
                        continue;
                    }
                    ?>
                    <article class="flex flex-col gap-2 lg:gap-3">
                        <?php if ($title !== '') : ?>
                            <h3 class="font-display text-quote text-brand-primary"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($title) : esc_html($title); ?></h3>
                        <?php endif; ?>
                        <?php if ($body !== '') : ?>
                            <div class="legal-content w-full">
                                <?php echo function_exists('luux_format_legal_html') ? luux_format_legal_html((string) $body) : wp_kses_post($body); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($has_table) : ?>
                            <div class="legal-content w-full overflow-x-auto">
                                <table class="legal-schedule-table">
                                    <?php if ($header_1 !== '' || $header_2 !== '' || $header_2_sub !== '') : ?>
                                        <thead>
                                            <?php if ($header_2_sub !== '') : ?>
                                                <tr>
                                                    <th scope="col" rowspan="2"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($header_1) : esc_html($header_1); ?></th>
                                                    <th scope="col"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($header_2) : esc_html($header_2); ?></th>
                                                </tr>
                                                <tr>
                                                    <th scope="col"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($header_2_sub) : esc_html($header_2_sub); ?></th>
                                                </tr>
                                            <?php else : ?>
                                                <tr>
                                                    <th scope="col"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($header_1) : esc_html($header_1); ?></th>
                                                    <th scope="col"><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($header_2) : esc_html($header_2); ?></th>
                                                </tr>
                                            <?php endif; ?>
                                        </thead>
                                    <?php endif; ?>
                                    <?php if ($table_rows !== []) : ?>
                                        <tbody>
                                            <?php foreach ($table_rows as $table_row) :
                                                if (! is_array($table_row)) {
                                                    continue;
                                                }

                                                $col_1 = isset($table_row['col_1']) ? (string) $table_row['col_1'] : '';
                                                $col_2 = isset($table_row['col_2']) ? (string) $table_row['col_2'] : '';

                                                if ($col_1 === '' && $col_2 === '') {
                                                    continue;
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($col_1) : esc_html($col_1); ?></td>
                                                    <td><?php echo function_exists('luux_esc_legal_text') ? luux_esc_legal_text($col_2) : esc_html($col_2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    <?php endif; ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
