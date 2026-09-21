<?php
/**
 * Layout: legal-header — page title + intro for Terms / Privacy / Cookies.
 */

$heading    = luux_sub_field('heading');
$intro      = luux_sub_field('intro');
$section_id = luux_sub_field('section_id');

$post_id   = get_the_ID();
$row_index = function_exists('luux_section_row_index') ? luux_section_row_index() : -1;

// Prefer direct postmeta when present — legal scalars are custom-saved.
if ($post_id && $row_index >= 0 && function_exists('luux_read_section_meta')) {
    foreach (['heading', 'intro', 'section_id'] as $name) {
        $from_meta = luux_read_section_meta((int) $post_id, $row_index, $name);

        if ($from_meta !== null && $from_meta !== '') {
            ${$name} = $from_meta;
        }
    }
}

// Fall back to stash when postmeta is empty (e.g. after a save that wrote layout shells only).
if ($post_id && $row_index >= 0) {
    $stash = get_post_meta((int) $post_id, '_luux_legal_header_stash', true);

    if (is_array($stash)) {
        $row_stash = null;

        if (isset($stash[(string) $row_index]) && is_array($stash[(string) $row_index])) {
            $row_stash = $stash[(string) $row_index];
        } else {
            // Stash may be keyed by a different index than the live FC row — use first match.
            foreach ($stash as $fields) {
                if (is_array($fields) && $fields !== []) {
                    $row_stash = $fields;
                    break;
                }
            }
        }

        if (is_array($row_stash)) {
            foreach (['heading', 'intro', 'section_id'] as $name) {
                if ((${$name} === '' || ${$name} === null || ${$name} === false) && ! empty($row_stash[$name])) {
                    ${$name} = $row_stash[$name];
                }
            }
        }
    }
}

if (! $heading && ! $intro) {
    return;
}

// Prevent the same legal header row rendering twice in one request (after content resolves).
static $luux_legal_header_rendered = [];
$render_key = (int) $post_id . ':' . (int) $row_index;

if ($post_id && $row_index >= 0 && isset($luux_legal_header_rendered[$render_key])) {
    return;
}

if ($post_id && $row_index >= 0) {
    $luux_legal_header_rendered[$render_key] = true;
}

// Hero already outputs the page h1 — legal header is a section title.
$heading_tag = (function_exists('luux_uses_hero_header') && luux_uses_hero_header()) ? 'h2' : 'h1';
?>

<section<?php echo $section_id ? ' id="' . esc_attr($section_id) . '"' : ''; ?> class="legal-header bg-brand-white py-8 lg:py-10">
    <div class="container-site flex flex-col items-start gap-4 lg:gap-5">
        <?php if ($heading) : ?>
            <<?php echo $heading_tag; ?> class="w-full font-display text-h3 text-brand-primary"><?php echo esc_html($heading); ?></<?php echo $heading_tag; ?>>
        <?php endif; ?>
        <?php if ($intro) : ?>
            <div class="legal-content w-full">
                <?php echo luux_format_legal_html((string) $intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses inside helper ?>
            </div>
        <?php endif; ?>
    </div>
</section>
