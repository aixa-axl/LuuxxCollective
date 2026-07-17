/**
 * Property Gallery layout admin save helpers.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'property_gallery';

    var FIELD_KEYS = {
        heading: 'field_luux_property_gallery_heading',
        text: 'field_luux_property_gallery_text',
        section_id: 'field_luux_property_gallery_section_id',
    };

    var IMAGE_SUB_KEYS = {
        image: 'field_luux_property_gallery_image',
        size: 'field_luux_property_gallery_size',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutPropertyGallery !== 'undefined' && luuxLayoutPropertyGallery.ajaxurl) {
            return luuxLayoutPropertyGallery.ajaxurl;
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

            if (value !== null && value !== undefined && value !== '') {
                fields[name] = String(value);
            }
        });

        return fields;
    }

    function readImages($layout) {
        var images = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_property_gallery_images"], .acf-field[data-name="images"]').first();

        if (!$repeater.length) {
            return images;
        }

        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var repeaterField = acf.getField($repeater);
            var $rows = repeaterField && typeof repeaterField.$rows === 'function'
                ? repeaterField.$rows()
                : $repeater.find('.acf-row').not('.acf-clone');

            $rows.each(function () {
                var $row = $(this);
                var item = {};

                $.each(IMAGE_SUB_KEYS, function (name, key) {
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

                    if (name === 'image') {
                        var imageId = normalizeAttachmentId(value);

                        if (imageId !== '') {
                            item.image = parseInt(imageId, 10);
                            item.field_luux_property_gallery_image = item.image;
                        }

                        return;
                    }

                    if (value !== null && value !== undefined && value !== '') {
                        item.size = String(value);
                        item.field_luux_property_gallery_size = item.size;
                    }
                });

                if (item.image && item.size) {
                    images.push(item);
                }
            });
        }

        if (images.length) {
            return images;
        }

        var bucket = {};
        var imagePattern = /\[(?:field_luux_property_gallery_images|images)\]\[(?:row-)?(\d+)\]\[(?:field_luux_property_gallery_)?(image)\]/;
        var sizePattern = /\[(?:field_luux_property_gallery_images|images)\]\[(?:row-)?(\d+)\]\[(?:field_luux_property_gallery_)?(size)\]/;

        $layout.find('input, select').each(function () {
            var name = this.name || '';
            var imageMatch = name.match(imagePattern);
            var sizeMatch = name.match(sizePattern);
            var match = imageMatch || sizeMatch;

            if (!match) {
                return;
            }

            var idx = match[1];
            var subName = match[2];
            var value = $(this).val();

            if (!bucket[idx]) {
                bucket[idx] = {};
            }

            if (subName === 'image') {
                var imageId = normalizeAttachmentId(value);

                if (imageId !== '') {
                    bucket[idx].image = imageId;
                }

                return;
            }

            if (value !== null && value !== undefined && String(value) !== '') {
                bucket[idx].size = String(value);
            }
        });

        Object.keys(bucket).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).forEach(function (key) {
            var raw = bucket[key];
            var imageId = normalizeAttachmentId(raw.image);

            if (imageId !== '' && raw.size) {
                images.push({
                    image: parseInt(imageId, 10),
                    field_luux_property_gallery_image: parseInt(imageId, 10),
                    size: raw.size,
                    field_luux_property_gallery_size: raw.size,
                });
            }
        });

        return images;
    }

    function readRowFields($layout) {
        expandLayout($layout);

        var fields = readScalarFields($layout);
        var images = readImages($layout);

        if (images.length) {
            fields.images_json = JSON.stringify(images);
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

        $('#luux-property-gallery-early').remove();

        var $wrap = $('<div id="luux-property-gallery-early" style="display:none" aria-hidden="true"></div>');

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                if (name === 'text' || name === 'images_json') {
                    $('<textarea>', {
                        name: 'luux_property_gallery[' + item.domIndex + '][' + name + ']',
                        'aria-hidden': 'true',
                    }).css({ position: 'absolute', left: '-9999px', height: '1px', width: '1px' })
                        .val(value)
                        .appendTo($wrap);
                    return;
                }

                $('<input>', {
                    type: 'hidden',
                    name: 'luux_property_gallery[' + item.domIndex + '][' + name + ']',
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
            action: 'luux_save_property_gallery_fields',
            nonce: luuxLayoutPropertyGallery.nonce,
            post_id: postId,
            row_index: rowIndex,
            row_nth: rowNth,
            fields: {},
        };

        $.each(fields, function (name, value) {
            if (name === 'images_json') {
                payload.images_json = value;
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
        return layout === LAYOUT || layout === 'layout_luux_property_gallery';
    }

    function mergeRowIntoAcfPayload(fields, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: LAYOUT };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (fields[name] === undefined || fields[name] === null || fields[name] === '') {
                return;
            }

            acfRow[key] = fields[name];
            acfRow[name] = fields[name];
        });

        if (fields.images_json) {
            try {
                var images = JSON.parse(fields.images_json);

                if ($.isArray(images) && images.length) {
                    acfRow.field_luux_property_gallery_images = images;
                    acfRow.images = images;
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

        return key.indexOf('field_luux_property_gallery_') === 0;
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
        if (initialized || typeof luuxLayoutPropertyGallery === 'undefined') {
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
