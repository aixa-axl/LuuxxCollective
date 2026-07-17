/**
 * Suite Grid layout admin save helpers.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'suite_grid';

    var FIELD_KEYS = {
        section_label: 'field_luux_suite_grid_section_label',
        heading: 'field_luux_suite_grid_heading',
        filter_label: 'field_luux_suite_grid_filter_label',
        section_id: 'field_luux_suite_grid_section_id',
    };

    var CATEGORY_SUB_KEYS = {
        name: 'field_luux_suite_grid_category_name',
    };

    var SUITE_SUB_KEYS = {
        category: 'field_luux_suite_grid_suite_category',
        image: 'field_luux_suite_grid_suite_image',
        hotel_label: 'field_luux_suite_grid_suite_hotel_label',
        title: 'field_luux_suite_grid_suite_title',
        description: 'field_luux_suite_grid_suite_description',
        link: 'field_luux_suite_grid_suite_link',
        vertical_offset: 'field_luux_suite_grid_suite_offset',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutSuiteGrid !== 'undefined' && luuxLayoutSuiteGrid.ajaxurl) {
            return luuxLayoutSuiteGrid.ajaxurl;
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

    function readSubFieldValue($fieldEl, name) {
        if (typeof acf === 'undefined' || typeof acf.getField !== 'function') {
            return null;
        }

        var field = acf.getField($fieldEl);

        if (!field || typeof field.val !== 'function') {
            return null;
        }

        var value = field.val();

        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (name === 'image') {
            var imageId = normalizeAttachmentId(value);

            return imageId !== '' ? parseInt(imageId, 10) : null;
        }

        if (name === 'link') {
            var link = normalizeLink(value);

            if (!link) {
                link = readLinkFromInputs($fieldEl);
            }

            return link;
        }

        return String(value);
    }

    function categoryFromBucket(raw) {
        var category = {};

        if (raw.name !== undefined && raw.name !== null && String(raw.name) !== '') {
            category.name = String(raw.name);
            category.field_luux_suite_grid_category_name = category.name;
        }

        return category;
    }

    function suiteFromBucket(raw) {
        var suite = {};

        if (raw.category !== undefined && raw.category !== null && String(raw.category) !== '') {
            suite.category = String(raw.category);
            suite.field_luux_suite_grid_suite_category = suite.category;
        }

        if (raw.image) {
            var imageId = normalizeAttachmentId(raw.image);

            if (imageId !== '') {
                suite.image = parseInt(imageId, 10);
                suite.field_luux_suite_grid_suite_image = suite.image;
            }
        }

        ['hotel_label', 'title', 'description'].forEach(function (name) {
            if (raw[name] !== undefined && raw[name] !== null && String(raw[name]) !== '') {
                suite[name] = String(raw[name]);
                suite['field_luux_suite_grid_suite_' + name] = suite[name];
            }
        });

        if (raw.link && raw.link.url) {
            suite.link = normalizeLink(raw.link);
            suite.field_luux_suite_grid_suite_link = suite.link;
        }

        if (raw.vertical_offset !== undefined && raw.vertical_offset !== null && String(raw.vertical_offset) !== '') {
            suite.vertical_offset = String(raw.vertical_offset);
            suite.field_luux_suite_grid_suite_offset = suite.vertical_offset;
        }

        return suite;
    }

    function readCategories($layout) {
        var categories = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_suite_grid_categories"], .acf-field[data-name="categories"]').first();

        if (!$repeater.length || typeof acf === 'undefined' || typeof acf.getField !== 'function') {
            return categories;
        }

        var repeaterField = acf.getField($repeater);
        var $rows = repeaterField && typeof repeaterField.$rows === 'function'
            ? repeaterField.$rows()
            : $repeater.find('.acf-row').not('.acf-clone');

        $rows.each(function () {
            var $row = $(this);
            var raw = {};

            $.each(CATEGORY_SUB_KEYS, function (name, key) {
                var $fieldEl = $row.find('.acf-field[data-key="' + key + '"]').first();

                if (!$fieldEl.length) {
                    return;
                }

                var value = readSubFieldValue($fieldEl, name);

                if (value !== null && value !== '') {
                    raw[name] = value;
                }
            });

            var category = categoryFromBucket(raw);

            if (!$.isEmptyObject(category)) {
                categories.push(category);
            }
        });

        if (categories.length) {
            return categories;
        }

        var bucket = {};
        var pattern = /\[(?:field_luux_suite_grid_categories|categories)\]\[(?:row-)?(\d+)\]\[(?:field_luux_suite_grid_category_)?(name)\]/;

        $layout.find('input, textarea').each(function () {
            var match = (this.name || '').match(pattern);

            if (!match) {
                return;
            }

            var idx = match[1];
            var value = $(this).val();

            if (value !== null && value !== undefined && String(value) !== '') {
                bucket[idx] = { name: String(value) };
            }
        });

        Object.keys(bucket).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).forEach(function (key) {
            var category = categoryFromBucket(bucket[key]);

            if (!$.isEmptyObject(category)) {
                categories.push(category);
            }
        });

        return categories;
    }

    function readSuites($layout) {
        var suites = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_suite_grid_suites"], .acf-field[data-name="suites"]').first();

        if (!$repeater.length || typeof acf === 'undefined' || typeof acf.getField !== 'function') {
            return suites;
        }

        var repeaterField = acf.getField($repeater);
        var $rows = repeaterField && typeof repeaterField.$rows === 'function'
            ? repeaterField.$rows()
            : $repeater.find('.acf-row').not('.acf-clone').filter(function () {
                return $(this).closest('.acf-field[data-name="categories"]').length === 0;
            });

        $rows.each(function () {
            var $row = $(this);

            if ($row.closest('.acf-field[data-name="categories"]').length) {
                return;
            }

            var raw = { link: { url: '', title: '', target: '' } };

            $.each(SUITE_SUB_KEYS, function (name, key) {
                var $fieldEl = $row.find('.acf-field[data-key="' + key + '"]').first();

                if (!$fieldEl.length) {
                    return;
                }

                if (name === 'link') {
                    var link = readSubFieldValue($fieldEl, name);

                    if (link) {
                        raw.link = link;
                    }

                    return;
                }

                var value = readSubFieldValue($fieldEl, name);

                if (value !== null && value !== '') {
                    raw[name] = value;
                }
            });

            var suite = suiteFromBucket(raw);

            if (!$.isEmptyObject(suite)) {
                suites.push(suite);
            }
        });

        if (suites.length) {
            return suites;
        }

        var bucket = {};
        var pattern = /\[(?:field_luux_suite_grid_suites|suites)\]\[(?:row-)?(\d+)\]\[(?:field_luux_suite_grid_suite_)?(category|image|hotel_label|title|description|link|vertical_offset|offset)\](?:\[(url|title|target)\])?/;

        $layout.find('input, textarea, select').each(function () {
            var match = (this.name || '').match(pattern);

            if (!match) {
                return;
            }

            var idx = match[1];
            var field = match[2] === 'offset' ? 'vertical_offset' : match[2];
            var linkPart = match[3] || '';
            var value = $(this).val();

            if (!bucket[idx]) {
                bucket[idx] = { link: { url: '', title: '', target: '' } };
            }

            if (field === 'link') {
                if (linkPart) {
                    bucket[idx].link[linkPart] = String(value || '');
                }

                return;
            }

            if (value === null || value === undefined || String(value) === '') {
                return;
            }

            bucket[idx][field] = value;
        });

        Object.keys(bucket).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).forEach(function (key) {
            var suite = suiteFromBucket(bucket[key]);

            if (!$.isEmptyObject(suite)) {
                suites.push(suite);
            }
        });

        return suites;
    }

    function readScalarFields($layout) {
        var fields = {};

        $.each(FIELD_KEYS, function (name, key) {
            var $fieldEl = $layout.find('.acf-field[data-key="' + key + '"]').first();

            if (!$fieldEl.length || typeof acf === 'undefined' || typeof acf.getField !== 'function') {
                return;
            }

            var field = acf.getField($fieldEl);

            if (!field || typeof field.val !== 'function') {
                return;
            }

            var value = field.val();

            if (value !== null && value !== undefined && value !== '') {
                fields[name] = String(value);
            }
        });

        return fields;
    }

    function readRowFields($layout) {
        expandLayout($layout);

        var fields = readScalarFields($layout);
        var categories = readCategories($layout);
        var suites = readSuites($layout);

        if (categories.length) {
            fields.categories_json = JSON.stringify(categories);
        }

        if (suites.length) {
            fields.suites_json = JSON.stringify(suites);
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

        $('#luux-suite-grid-early').remove();

        var $wrap = $('<div id="luux-suite-grid-early" style="display:none" aria-hidden="true"></div>');

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                if (name === 'categories_json' || name === 'suites_json') {
                    $('<textarea>', {
                        name: 'luux_suite_grid[' + item.domIndex + '][' + name + ']',
                        'aria-hidden': 'true',
                    }).css({ position: 'absolute', left: '-9999px', height: '1px', width: '1px' })
                        .val(value)
                        .appendTo($wrap);
                    return;
                }

                $('<input>', {
                    type: 'hidden',
                    name: 'luux_suite_grid[' + item.domIndex + '][' + name + ']',
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
            action: 'luux_save_suite_grid_fields',
            nonce: luuxLayoutSuiteGrid.nonce,
            post_id: postId,
            row_index: rowIndex,
            row_nth: rowNth,
            fields: {},
        };

        $.each(fields, function (name, value) {
            if (name === 'categories_json') {
                payload.categories_json = value;
                payload.fields[name] = value;
                return;
            }

            if (name === 'suites_json') {
                payload.suites_json = value;
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
        return layout === LAYOUT || layout === 'layout_luux_suite_grid';
    }

    function repeaterFromFields(fields, jsonKey, fieldKey, nameKey) {
        if (!fields || !fields[jsonKey]) {
            return [];
        }

        try {
            var decoded = JSON.parse(fields[jsonKey]);

            return $.isArray(decoded) ? decoded : [];
        } catch (e) {
            return [];
        }
    }

    function mergeRowIntoAcfPayload(fields, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: LAYOUT };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (fields[name]) {
                acfRow[key] = fields[name];
                acfRow[name] = fields[name];
            }
        });

        var categories = repeaterFromFields(fields, 'categories_json', 'field_luux_suite_grid_categories', 'categories');

        if (categories.length) {
            acfRow.field_luux_suite_grid_categories = categories;
            acfRow.categories = categories;
        }

        var suites = repeaterFromFields(fields, 'suites_json', 'field_luux_suite_grid_suites', 'suites');

        if (suites.length) {
            acfRow.field_luux_suite_grid_suites = suites;
            acfRow.suites = suites;
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

        return key.indexOf('field_luux_suite_grid_') === 0;
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
        if (initialized || typeof luuxLayoutSuiteGrid === 'undefined') {
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
