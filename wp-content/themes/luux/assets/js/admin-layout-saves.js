/**
 * Homepage layout admin save helpers.
 * Block editor + legacy imports can drop flexible-content fields from the save payload.
 */
(function ($) {
    'use strict';

    var savingRows = false;
    var initialized = false;

    function getConfig() {
        return typeof luuxLayoutSaves !== 'undefined' ? luuxLayoutSaves : null;
    }

    function getLayouts() {
        var config = getConfig();

        return config && config.layouts ? config.layouts : {};
    }

    function getAjaxUrl() {
        var config = getConfig();

        if (config && config.ajaxurl) {
            return config.ajaxurl;
        }

        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }

        return '';
    }

    function getPageSectionsFlex() {
        return $('.acf-field[data-key="field_luux_page_sections"] .acf-flexible-content, .acf-field[data-name="page_sections"] .acf-flexible-content').first();
    }

    function getFlexibleLayouts($flex) {
        var layouts = getLayouts();
        var counts = {};

        Object.keys(layouts).forEach(function (slug) {
            counts[slug] = 0;
        });

        var items = [];

        $flex.find('> .values > .layout').each(function (domIndex) {
            var $layout = $(this);
            var layout = $layout.attr('data-layout') || '';
            var rowNth = -1;

            if (Object.prototype.hasOwnProperty.call(counts, layout)) {
                rowNth = counts[layout];
                counts[layout] += 1;
            }

            items.push({
                domIndex: domIndex,
                rowNth: rowNth,
                layout: layout,
                $el: $layout,
            });
        });

        return items;
    }

    function layoutConfigForSlug(slug) {
        var layouts = getLayouts();

        return layouts[slug] || null;
    }

    function fieldNameForKey(layoutConfig, key) {
        var name = '';

        if (!layoutConfig || !layoutConfig.fieldKeys) {
            return name;
        }

        $.each(layoutConfig.fieldKeys, function (fieldName, fieldKey) {
            if (fieldKey === key) {
                name = fieldName;
            }
        });

        return name;
    }

    function fieldTypeForName(layoutConfig, name) {
        if (!layoutConfig || !layoutConfig.fieldTypes) {
            return 'scalar';
        }

        return layoutConfig.fieldTypes[name] || 'scalar';
    }

    function readFieldValueFromAcf($fieldEl, fieldType) {
        if (typeof acf === 'undefined' || typeof acf.getField !== 'function') {
            return '';
        }

        var field = acf.getField($fieldEl);

        if (!field || typeof field.val !== 'function') {
            return '';
        }

        var value = field.val();

        if (value === null || value === undefined || value === '') {
            return '';
        }

        if (fieldType === 'link' && typeof value === 'object' && value.url) {
            return JSON.stringify({
                url: value.url || '',
                title: value.title || '',
                target: value.target || '',
            });
        }

        if (typeof value === 'object' && value.id) {
            return String(value.id);
        }

        if (typeof value === 'object' && value.ID) {
            return String(value.ID);
        }

        if (typeof value === 'boolean') {
            return value ? '1' : '0';
        }

        return String(value);
    }

    function sanitizeRowFields(slug, fields) {
        if (slug === 'hero') {
            var mediaType = fields.media_type || 'image';

            if (mediaType === 'video') {
                delete fields.background_image;
            } else {
                delete fields.background_video;
            }
        }

        return fields;
    }

    function readRowFields($layout, layoutConfig) {
        var fields = {};
        var slug = layoutConfig ? layoutConfig.slug : '';

        $layout.find('> .acf-fields > .acf-field, .acf-fields > .acf-field').each(function () {
            var $fieldEl = $(this);
            var key = $fieldEl.attr('data-key') || '';
            var name = fieldNameForKey(layoutConfig, key);

            if (!name) {
                return;
            }

            var fieldType = fieldTypeForName(layoutConfig, name);
            var value = readFieldValueFromAcf($fieldEl, fieldType);

            if (value !== '') {
                fields[name] = value;
            }
        });

        return sanitizeRowFields(slug, fields);
    }

    function getLayoutRows(slug) {
        var $flex = getPageSectionsFlex();

        if (!$flex.length) {
            return [];
        }

        return getFlexibleLayouts($flex).filter(function (item) {
            return item.layout === slug;
        });
    }

    function injectEarlyFields() {
        var $form = $('#post');

        if (!$form.length) {
            $form = $('form').has('#post_ID').first();
        }

        if (!$form.length) {
            return;
        }

        $('#luux-layout-early').remove();

        var $wrap = $('<div id="luux-layout-early" style="display:none" aria-hidden="true"></div>');
        var layouts = getLayouts();

        $.each(layouts, function (slug) {
            var layoutConfig = layoutConfigForSlug(slug);
            var rows = getLayoutRows(slug);

            rows.forEach(function (item) {
                var fields = readRowFields(item.$el, layoutConfig);

                $.each(fields, function (name, value) {
                    $('<input>', {
                        type: 'hidden',
                        name: 'luux_layout_fields[' + slug + '][' + item.domIndex + '][' + name + ']',
                        value: value,
                    }).appendTo($wrap);
                });
            });
        });

        if ($wrap.children().length) {
            $form.prepend($wrap);
        }
    }

    function saveRowAjax(slug, rowIndex, rowNth, fields, async) {
        var config = getConfig();
        var postId = $('#post_ID').val();
        var url = getAjaxUrl();

        if (!config || !url || !postId || !fields || $.isEmptyObject(fields)) {
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: url,
            method: 'POST',
            async: async !== false,
            data: {
                action: 'luux_save_layout_fields',
                nonce: config.nonce,
                layout: slug,
                post_id: postId,
                row_index: rowIndex,
                row_nth: rowNth,
                fields: fields,
            },
        });
    }

    function stashAllLayoutRows(async) {
        if (savingRows) {
            return $.Deferred().resolve().promise();
        }

        savingRows = true;

        var chain = $.Deferred().resolve().promise();
        var layouts = getLayouts();

        $.each(layouts, function (slug) {
            var layoutConfig = layoutConfigForSlug(slug);
            var rows = getLayoutRows(slug);

            rows.forEach(function (item) {
                var fields = readRowFields(item.$el, layoutConfig);

                if ($.isEmptyObject(fields)) {
                    return;
                }

                chain = chain.then(function () {
                    return saveRowAjax(slug, item.domIndex, item.rowNth, fields, async);
                });
            });
        });

        chain.always(function () {
            savingRows = false;
        });

        return chain;
    }

    function mergeRowIntoAcfPayload(layoutConfig, fields, acfRow) {
        if (!layoutConfig) {
            return acfRow;
        }

        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: layoutConfig.slug };
        }

        $.each(layoutConfig.fieldKeys, function (name, key) {
            if (fields[name]) {
                acfRow[key] = fields[name];
                acfRow[name] = fields[name];
            }
        });

        return acfRow;
    }

    function layoutMatchesRow(layoutConfig, layout) {
        if (!layoutConfig) {
            return false;
        }

        var keys = layoutConfig.layoutKeys || [layoutConfig.slug];

        return keys.indexOf(layout) !== -1;
    }

    function mergeDomLayoutsIntoRestPayload(data) {
        if (!data || typeof data !== 'object') {
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

        var layouts = getLayouts();

        $.each(layouts, function (slug, layoutConfig) {
            var rows = getLayoutRows(slug);
            var nth = 0;

            $.each(fc, function (key, row) {
                if (!row || typeof row !== 'object') {
                    return;
                }

                var layout = row.acf_fc_layout || '';

                if (!layoutMatchesRow(layoutConfig, layout)) {
                    return;
                }

                var domRow = rows[nth];

                if (domRow) {
                    fc[key] = mergeRowIntoAcfPayload(layoutConfig, readRowFields(domRow.$el, layoutConfig), row);
                }

                nth += 1;
            });
        });

        data.meta.acf[fcKey] = fc;
        data.acf[fcKey] = fc;

        return data;
    }

    function mergeDomLayoutsIntoAcfData(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }

        if (!data.acf || typeof data.acf !== 'object') {
            data.acf = {};
        }

        var fcKey = 'field_luux_page_sections';
        var fc = data.acf[fcKey];

        if (!fc || typeof fc !== 'object') {
            return data;
        }

        var layouts = getLayouts();

        $.each(layouts, function (slug, layoutConfig) {
            var rows = getLayoutRows(slug);
            var nth = 0;

            $.each(fc, function (key, row) {
                if (!row || typeof row !== 'object') {
                    return;
                }

                var layout = row.acf_fc_layout || '';

                if (!layoutMatchesRow(layoutConfig, layout)) {
                    return;
                }

                var domRow = rows[nth];

                if (domRow) {
                    fc[key] = mergeRowIntoAcfPayload(layoutConfig, readRowFields(domRow.$el, layoutConfig), row);
                }

                nth += 1;
            });
        });

        data.acf[fcKey] = fc;

        return data;
    }

    function layoutSlugForField(field) {
        if (!field || typeof field.get !== 'function') {
            return '';
        }

        var key = field.get('key') || '';
        var layouts = getLayouts();

        var matched = '';

        $.each(layouts, function (slug, layoutConfig) {
            if (!layoutConfig.fieldKeys) {
                return;
            }

            $.each(layoutConfig.fieldKeys, function (name, fieldKey) {
                if (fieldKey === key) {
                    matched = slug;
                }
            });
        });

        return matched;
    }

    function layoutIndexForField(field, slug) {
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
            if ($(this).attr('data-layout') === slug) {
                if (index === domIndex) {
                    rowNth = count;
                }
                count += 1;
            }
        });

        return { domIndex: domIndex, rowNth: rowNth };
    }

    function handleFieldChange(field) {
        var slug = layoutSlugForField(field);

        if (!slug) {
            return;
        }

        var indices = layoutIndexForField(field, slug);

        if (indices.domIndex < 0) {
            return;
        }

        var layoutConfig = layoutConfigForSlug(slug);
        var $layout = field.$el.closest('.layout');
        var fields = readRowFields($layout, layoutConfig);

        saveRowAjax(slug, indices.domIndex, indices.rowNth, fields, true);
    }

    function beforeSave() {
        injectEarlyFields();
        return stashAllLayoutRows(false);
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
                options.data = mergeDomLayoutsIntoRestPayload(options.data);
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
        if (initialized || !getConfig()) {
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
                return mergeDomLayoutsIntoAcfData(data);
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
