/**
 * Reviews layout admin save helpers.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'reviews';

    var FIELD_KEYS = {
        heading: 'field_luux_reviews_heading',
        view_all_link: 'field_luux_reviews_view_all_link',
    };

    var ITEM_SUB_KEYS = {
        rating: 'field_luux_reviews_rating',
        quote: 'field_luux_reviews_quote',
        name: 'field_luux_reviews_name',
        date: 'field_luux_reviews_date',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutReviews !== 'undefined' && luuxLayoutReviews.ajaxurl) {
            return luuxLayoutReviews.ajaxurl;
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

    function normalizeLink(value) {
        if (!value || typeof value !== 'object' || !value.url) {
            return null;
        }

        var title = String(value.title || '');
        title = title.replace(/\\u2192/g, '→').replace(/u2192/g, '→');

        return {
            url: String(value.url || ''),
            title: title,
            target: String(value.target || ''),
        };
    }

    function readLinkFromInputs($fieldEl) {
        var link = { url: '', title: '', target: '' };

        $($fieldEl).find('input').each(function () {
            var name = this.name || '';
            var value = $(this).val() || '';

            if (/\[url\]$/.test(name)) {
                link.url = String(value);
            } else if (/\[title\]$/.test(name)) {
                link.title = String(value);
            } else if (/\[target\]$/.test(name)) {
                link.target = String(value);
            }
        });

        return normalizeLink(link);
    }

    function readScalarFields($layout) {
        var fields = {};

        $.each(FIELD_KEYS, function (name, key) {
            var $fieldEl = $layout.find('.acf-field[data-key="' + key + '"]').first();

            if (!$fieldEl.length) {
                $fieldEl = $layout.find('.acf-field[data-name="' + name + '"]').first();
            }

            if (!$fieldEl.length) {
                return;
            }

            if (name === 'view_all_link') {
                var link = null;

                if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
                    var field = acf.getField($fieldEl);

                    if (field && typeof field.val === 'function') {
                        link = normalizeLink(field.val());
                    }
                }

                if (!link) {
                    link = readLinkFromInputs($fieldEl);
                }

                if (link) {
                    fields.view_all_link_json = JSON.stringify(link);
                }

                return;
            }

            if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
                var textField = acf.getField($fieldEl);

                if (textField && typeof textField.val === 'function') {
                    var value = textField.val();

                    if (value !== null && value !== undefined && value !== '') {
                        fields[name] = String(value);
                        return;
                    }
                }
            }

            var $input = $fieldEl.find('input[type="text"], textarea').first();

            if ($input.length && $input.val()) {
                fields[name] = String($input.val());
            }
        });

        return fields;
    }

    function readTestimonials($layout) {
        var items = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_reviews_testimonials"], .acf-field[data-name="testimonials"]').first();

        if (!$repeater.length) {
            return items;
        }

        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var repeaterField = acf.getField($repeater);
            var $rows = repeaterField && typeof repeaterField.$rows === 'function'
                ? repeaterField.$rows()
                : $repeater.find('.acf-row').not('.acf-clone');

            $rows.each(function () {
                var $row = $(this);
                var item = {};

                $.each(ITEM_SUB_KEYS, function (name, key) {
                    var $fieldEl = $row.find('.acf-field[data-key="' + key + '"]').first();

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

                    if (name === 'rating') {
                        item.rating = parseInt(value, 10);
                        item.field_luux_reviews_rating = item.rating;
                        return;
                    }

                    item[name] = String(value);
                    item['field_luux_reviews_' + name] = item[name];
                });

                if (!$.isEmptyObject(item)) {
                    items.push(item);
                }
            });
        }

        if (items.length) {
            return items;
        }

        var bucket = {};
        var pattern = /\[(?:field_luux_reviews_testimonials|testimonials)\]\[(?:row-)?(\d+)\]\[(?:field_luux_reviews_)?(rating|quote|name|date)\]/;

        $layout.find('input, textarea').each(function () {
            var match = (this.name || '').match(pattern);

            if (!match) {
                return;
            }

            var idx = match[1];
            var field = match[2];
            var value = $(this).val();

            if (value === null || value === undefined || String(value) === '') {
                return;
            }

            if (!bucket[idx]) {
                bucket[idx] = {};
            }

            bucket[idx][field] = value;
        });

        Object.keys(bucket).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).forEach(function (key) {
            var raw = bucket[key];
            var item = {};

            if (raw.rating !== undefined && raw.rating !== '') {
                item.rating = parseInt(raw.rating, 10);
                item.field_luux_reviews_rating = item.rating;
            }

            ['quote', 'name', 'date'].forEach(function (name) {
                if (raw[name]) {
                    item[name] = String(raw[name]);
                    item['field_luux_reviews_' + name] = item[name];
                }
            });

            if (!$.isEmptyObject(item)) {
                items.push(item);
            }
        });

        return items;
    }

    function readRowFields($layout) {
        expandLayout($layout);

        var fields = readScalarFields($layout);
        var items = readTestimonials($layout);

        if (items.length) {
            fields.testimonials_json = JSON.stringify(items);
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

        $('#luux-reviews-early').remove();

        var $wrap = $('<div id="luux-reviews-early" style="display:none" aria-hidden="true"></div>');

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                if (name.indexOf('_json') !== -1) {
                    $('<textarea>', {
                        name: 'luux_reviews[' + item.domIndex + '][' + name + ']',
                        'aria-hidden': 'true',
                    }).css({ position: 'absolute', left: '-9999px', height: '1px', width: '1px' })
                        .val(value)
                        .appendTo($wrap);
                    return;
                }

                $('<input>', {
                    type: 'hidden',
                    name: 'luux_reviews[' + item.domIndex + '][' + name + ']',
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
            action: 'luux_save_reviews_fields',
            nonce: luuxLayoutReviews.nonce,
            post_id: postId,
            row_index: rowIndex,
            row_nth: rowNth,
            fields: {},
        };

        $.each(fields, function (name, value) {
            if (name === 'view_all_link_json' || name === 'testimonials_json') {
                payload[name] = value;
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
        return layout === LAYOUT || layout === 'layout_luux_reviews';
    }

    function mergeRowIntoAcfPayload(fields, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: LAYOUT };
        }

        if (fields.heading) {
            acfRow.field_luux_reviews_heading = fields.heading;
            acfRow.heading = fields.heading;
        }

        if (fields.view_all_link_json) {
            try {
                var link = JSON.parse(fields.view_all_link_json);

                if (link && link.url) {
                    acfRow.field_luux_reviews_view_all_link = link;
                    acfRow.view_all_link = link;
                }
            } catch (e) {
                // Ignore.
            }
        }

        if (fields.testimonials_json) {
            try {
                var items = JSON.parse(fields.testimonials_json);

                if ($.isArray(items) && items.length) {
                    acfRow.field_luux_reviews_testimonials = items;
                    acfRow.testimonials = items;
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

        return key.indexOf('field_luux_reviews_') === 0;
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
        if (initialized || typeof luuxLayoutReviews === 'undefined') {
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
