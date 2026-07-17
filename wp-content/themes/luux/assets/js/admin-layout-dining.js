/**
 * Dining layout admin save helpers.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'dining';

    var FIELD_KEYS = {
        section_label: 'field_luux_dining_section_label',
        heading: 'field_luux_dining_heading',
        text: 'field_luux_dining_text',
        image_top_left: 'field_luux_dining_image_top_left',
        image_bottom_left: 'field_luux_dining_image_bottom_left',
        image_top: 'field_luux_dining_image_top',
        image_bottom: 'field_luux_dining_image_bottom',
        hero_media_type: 'field_luux_dining_hero_media_type',
        image_hero: 'field_luux_dining_image_hero',
        hero_video: 'field_luux_dining_hero_video',
        section_id: 'field_luux_dining_section_id',
    };

    var HIGHLIGHT_SUB_KEYS = {
        text: 'field_luux_dining_highlight_text',
    };

    var MEDIA_FIELDS = [
        'image_top_left',
        'image_bottom_left',
        'image_top',
        'image_bottom',
        'image_hero',
        'hero_video',
    ];

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutDining !== 'undefined' && luuxLayoutDining.ajaxurl) {
            return luuxLayoutDining.ajaxurl;
        }

        return typeof ajaxurl !== 'undefined' ? ajaxurl : '';
    }

    function getPageSectionsFlex() {
        return $('.acf-field[data-key="field_luux_page_sections"] .acf-flexible-content, .acf-field[data-name="page_sections"] .acf-flexible-content').first();
    }

    function getLayoutRows() {
        var $flex = getPageSectionsFlex();
        var rows = [];
        var nth = 0;

        if (!$flex.length) {
            return rows;
        }

        $flex.find('> .values > .layout').each(function (domIndex) {
            var $layout = $(this);
            var layout = $layout.attr('data-layout') || '';

            if (layout !== LAYOUT) {
                return;
            }

            rows.push({
                domIndex: domIndex,
                rowNth: nth,
                layout: layout,
                $el: $layout,
            });
            nth += 1;
        });

        return rows;
    }

    function expandLayout($layout) {
        if ($layout && $layout.length && $layout.hasClass('-collapsed')) {
            $layout.removeClass('-collapsed');
            $layout.find('> .acf-fields, > .acf-fc-layout-body').show();
        }
    }

    function normalizeAttachmentId(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        if (typeof value === 'object') {
            if (value.id) {
                return String(value.id);
            }

            if (value.ID) {
                return String(value.ID);
            }
        }

        if (typeof value === 'number' || (typeof value === 'string' && /^\d+$/.test(value))) {
            return String(value);
        }

        return '';
    }

    function readTextarea($fieldEl) {
        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var field = acf.getField($fieldEl);

            if (field && typeof field.val === 'function') {
                var value = field.val();

                if (value !== null && value !== undefined && value !== '') {
                    return String(value);
                }
            }
        }

        var $textarea = $fieldEl.find('textarea').first();

        if ($textarea.length && $textarea.val()) {
            return String($textarea.val());
        }

        return '';
    }

    function readHighlights($layout) {
        var highlights = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_dining_highlights"], .acf-field[data-name="highlights"]').first();

        if (!$repeater.length) {
            return highlights;
        }

        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var repeaterField = acf.getField($repeater);
            var $rows = repeaterField && typeof repeaterField.$rows === 'function'
                ? repeaterField.$rows()
                : $repeater.find('.acf-row').not('.acf-clone');

            $rows.each(function () {
                var $row = $(this);
                var item = {};

                $.each(HIGHLIGHT_SUB_KEYS, function (name, key) {
                    var $fieldEl = $row.find('.acf-field[data-key="' + key + '"]').first();

                    if (!$fieldEl.length) {
                        $fieldEl = $row.find('.acf-field[data-name="' + name + '"]').first();
                    }

                    if (!$fieldEl.length || typeof acf.getField !== 'function') {
                        return;
                    }

                    var field = acf.getField($fieldEl);

                    if (!field || typeof field.val !== 'function') {
                        return;
                    }

                    var value = field.val();

                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    item.text = String(value);
                    item.field_luux_dining_highlight_text = item.text;
                });

                if (!$.isEmptyObject(item)) {
                    highlights.push(item);
                }
            });
        }

        if (highlights.length) {
            return highlights;
        }

        var bucket = {};
        var pattern = /\[(?:field_luux_dining_highlights|highlights)\]\[(?:row-)?(\d+)\]\[(?:field_luux_dining_highlight_)?(text)\]/;

        $layout.find('input, textarea').each(function () {
            var match = (this.name || '').match(pattern);

            if (!match) {
                return;
            }

            var idx = match[1];
            var value = $(this).val();

            if (value === null || value === undefined || String(value) === '') {
                return;
            }

            if (!bucket[idx]) {
                bucket[idx] = {};
            }

            bucket[idx].text = String(value);
        });

        Object.keys(bucket).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).forEach(function (key) {
            var raw = bucket[key];

            if (raw.text) {
                highlights.push({
                    text: raw.text,
                    field_luux_dining_highlight_text: raw.text,
                });
            }
        });

        return highlights;
    }

    function readRowFields($layout) {
        expandLayout($layout);

        var fields = {};

        $.each(FIELD_KEYS, function (name, key) {
            var $fieldEl = $layout.find('.acf-field[data-key="' + key + '"]').first();

            if (!$fieldEl.length) {
                $fieldEl = $layout.find('.acf-field[data-name="' + name + '"]').first();
            }

            if (!$fieldEl.length) {
                return;
            }

            if (name === 'text') {
                var textValue = readTextarea($fieldEl);

                if (textValue) {
                    fields.text = textValue;
                }

                return;
            }

            if (typeof acf === 'undefined' || typeof acf.getField !== 'function') {
                return;
            }

            var field = acf.getField($fieldEl);

            if (!field || typeof field.val !== 'function') {
                return;
            }

            var value = field.val();

            if (MEDIA_FIELDS.indexOf(name) !== -1) {
                var id = normalizeAttachmentId(value);

                if (id !== '') {
                    fields[name] = id;
                }

                return;
            }

            if (value !== null && value !== undefined && value !== '') {
                fields[name] = String(value);
            }
        });

        var highlights = readHighlights($layout);

        if (highlights.length) {
            fields.highlights_json = JSON.stringify(highlights);
        }

        var heroMediaType = fields.hero_media_type || 'image';

        if (heroMediaType === 'video') {
            delete fields.image_hero;
        } else {
            delete fields.hero_video;
        }

        if (fields.hero_video && !fields.hero_media_type) {
            fields.hero_media_type = 'video';
        }

        return fields;
    }

    function injectEarlyFields() {
        var $form = $('#post');

        if (!$form.length) {
            $form = $('form').has('#post_ID').first();
        }

        if (!$form.length) {
            return;
        }

        $('#luux-dining-early').remove();

        var $wrap = $('<div id="luux-dining-early" style="display:none" aria-hidden="true"></div>');

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                if (name === 'text' || name === 'highlights_json') {
                    $('<textarea>', {
                        name: 'luux_dining[' + item.domIndex + '][' + name + ']',
                        'aria-hidden': 'true',
                    }).css({ position: 'absolute', left: '-9999px', height: '1px', width: '1px' })
                        .val(value)
                        .appendTo($wrap);
                    return;
                }

                $('<input>', {
                    type: 'hidden',
                    name: 'luux_dining[' + item.domIndex + '][' + name + ']',
                    value: value,
                }).appendTo($wrap);
            });
        });

        if ($wrap.children().length) {
            $form.prepend($wrap);
        }
    }

    function saveRowAjax(rowIndex, rowNth, fields, async) {
        var postId = $('#post_ID').val();
        var url = getAjaxUrl();

        if (!url || !postId || !fields || $.isEmptyObject(fields)) {
            return $.Deferred().resolve().promise();
        }

        var payload = {
            action: 'luux_save_dining_fields',
            nonce: luuxLayoutDining.nonce,
            post_id: postId,
            row_index: rowIndex,
            row_nth: rowNth,
            fields: {},
        };

        $.each(fields, function (name, value) {
            if (name === 'highlights_json') {
                payload.highlights_json = value;
                payload.fields[name] = value;
                return;
            }

            payload.fields[name] = value;
        });

        return $.ajax({
            url: url,
            method: 'POST',
            async: async !== false,
            data: payload,
        });
    }

    function stashAllRows(async) {
        if (savingRows) {
            return $.Deferred().resolve().promise();
        }

        savingRows = true;

        var chain = $.Deferred().resolve().promise();

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            if ($.isEmptyObject(fields)) {
                return;
            }

            chain = chain.then(function () {
                return saveRowAjax(item.domIndex, item.rowNth, fields, async);
            });
        });

        chain.always(function () {
            savingRows = false;
        });

        return chain;
    }

    function layoutMatches(layout) {
        return layout === LAYOUT || layout === 'layout_luux_dining';
    }

    function mergeRowIntoAcfPayload(fields, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: LAYOUT };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (fields[name] === undefined || fields[name] === null || fields[name] === '') {
                return;
            }

            if (MEDIA_FIELDS.indexOf(name) !== -1) {
                var id = parseInt(fields[name], 10);

                if (id > 0) {
                    acfRow[key] = id;
                    acfRow[name] = id;
                }

                return;
            }

            acfRow[key] = fields[name];
            acfRow[name] = fields[name];
        });

        if (fields.highlights_json) {
            try {
                var highlights = JSON.parse(fields.highlights_json);

                if ($.isArray(highlights) && highlights.length) {
                    acfRow.field_luux_dining_highlights = highlights;
                    acfRow.highlights = highlights;
                }
            } catch (e) {
                // Ignore.
            }
        }

        return acfRow;
    }

    function mergeDomIntoRestPayload(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }

        var rows = getLayoutRows();

        if (!rows.length) {
            return data;
        }

        if (!data.meta || typeof data.meta !== 'object') {
            data.meta = {};
        }

        if (!data.meta.acf || typeof data.meta.acf !== 'object') {
            data.meta.acf = {};
        }

        if (!data.acf || typeof data.acf !== 'object') {
            data.acf = {};
        }

        var fcKey = 'field_luux_page_sections';
        var fc = data.meta.acf[fcKey] || data.acf[fcKey];

        if (!fc || typeof fc !== 'object') {
            fc = {};
        }

        var matched = 0;

        $.each(fc, function (key, row) {
            if (!row || typeof row !== 'object' || !layoutMatches(row.acf_fc_layout || '')) {
                return;
            }

            var domRow = rows[matched];

            if (domRow) {
                fc[key] = mergeRowIntoAcfPayload(readRowFields(domRow.$el), row);
            }

            matched += 1;
        });

        if (matched === 0) {
            rows.forEach(function (domRow) {
                fc[String(domRow.domIndex)] = mergeRowIntoAcfPayload(
                    readRowFields(domRow.$el),
                    { acf_fc_layout: LAYOUT }
                );
            });
        }

        data.meta.acf[fcKey] = fc;
        data.acf[fcKey] = fc;

        return data;
    }

    function mergeDomIntoAcfData(data) {
        if (!data || typeof data !== 'object' || !data.acf || typeof data.acf !== 'object') {
            return data;
        }

        var fcKey = 'field_luux_page_sections';
        var fc = data.acf[fcKey];

        if (!fc || typeof fc !== 'object') {
            return data;
        }

        var rows = getLayoutRows();
        var nth = 0;

        $.each(fc, function (key, row) {
            if (!row || typeof row !== 'object' || !layoutMatches(row.acf_fc_layout || '')) {
                return;
            }

            var domRow = rows[nth];

            if (domRow) {
                fc[key] = mergeRowIntoAcfPayload(readRowFields(domRow.$el), row);
            }

            nth += 1;
        });

        data.acf[fcKey] = fc;

        return data;
    }

    function layoutIndexForField(field) {
        var $layout = field.$el.closest('.layout');

        if (!$layout.length) {
            return { domIndex: -1, rowNth: -1 };
        }

        var $flex = $layout.closest('.acf-flexible-content');

        if (!$flex.length) {
            return { domIndex: -1, rowNth: -1 };
        }

        var domIndex = $flex.find('> .values > .layout').index($layout);
        var rowNth = -1;
        var count = 0;

        $flex.find('> .values > .layout').each(function (index) {
            if ($(this).attr('data-layout') === LAYOUT) {
                if (index === domIndex) {
                    rowNth = count;
                }
                count += 1;
            }
        });

        return { domIndex: domIndex, rowNth: rowNth };
    }

    function isLayoutField(field) {
        if (!field || typeof field.get !== 'function') {
            return false;
        }

        var key = field.get('key') || '';

        if (Object.values(FIELD_KEYS).indexOf(key) !== -1) {
            return true;
        }

        return key.indexOf('field_luux_dining_') === 0;
    }

    function handleFieldChange(field) {
        if (!isLayoutField(field)) {
            return;
        }

        var indices = layoutIndexForField(field);

        if (indices.domIndex < 0) {
            return;
        }

        saveRowAjax(indices.domIndex, indices.rowNth, readRowFields(field.$el.closest('.layout')), true);
    }

    function beforeSave() {
        injectEarlyFields();
        return stashAllRows(false);
    }

    function bindRestApiSave() {
        if (!window.wp || !wp.apiFetch || typeof wp.apiFetch.use !== 'function') {
            return;
        }

        wp.apiFetch.use(function (options, next) {
            var path = options.path || '';
            var method = (options.method || 'GET').toUpperCase();
            var isPageWrite = /\/wp\/v2\/pages\/\d+/.test(path) && (method === 'POST' || method === 'PUT' || method === 'PATCH');

            if (isPageWrite && options.data) {
                options.data = mergeDomIntoRestPayload(options.data);
            }

            return next(options);
        });
    }

    function bindBlockEditorSave() {
        var attempts = 0;

        function tryBind() {
            attempts += 1;

            if (!window.wp || !wp.data || !wp.data.subscribe || !wp.data.select) {
                if (attempts < 100) {
                    window.setTimeout(tryBind, 100);
                }
                return;
            }

            var wasSaving = false;

            wp.data.subscribe(function () {
                var editor = wp.data.select('core/editor');

                if (!editor || typeof editor.isSavingPost !== 'function') {
                    return;
                }

                var isSaving = editor.isSavingPost();
                var isAutosave = typeof editor.isAutosavingPost === 'function' && editor.isAutosavingPost();

                if (isSaving && !isAutosave && !wasSaving) {
                    beforeSave();
                }

                wasSaving = isSaving;
            });
        }

        tryBind();
    }

    function init() {
        if (initialized || typeof luuxLayoutDining === 'undefined') {
            return;
        }

        initialized = true;

        $('#post').on('submit', function () {
            beforeSave();
        });

        acf.addAction('submit', function () {
            beforeSave();
        });

        acf.addAction('prepare', function () {
            injectEarlyFields();
        });

        acf.addAction('change', handleFieldChange);
        acf.addAction('select', handleFieldChange);
        acf.addAction('remove', handleFieldChange);

        if (acf.addFilter) {
            acf.addFilter('prepare_for_ajax', function (data) {
                injectEarlyFields();
                return mergeDomIntoAcfData(data);
            });
        }

        bindBlockEditorSave();
        bindRestApiSave();
    }

    if (typeof acf !== 'undefined' && typeof acf.addAction === 'function') {
        acf.addAction('ready', init);

        $(function () {
            window.setTimeout(function () {
                if (!initialized) {
                    init();
                }
            }, 500);
        });
    } else {
        $(function () {
            var attempts = 0;
            var timer = window.setInterval(function () {
                attempts += 1;

                if (typeof acf !== 'undefined' && typeof acf.addAction === 'function') {
                    window.clearInterval(timer);
                    acf.addAction('ready', init);
                }

                if (attempts > 50) {
                    window.clearInterval(timer);
                }
            }, 100);
        });
    }
})(jQuery);
