/**
 * Hotel Showcase layout admin save helpers.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'hotel_showcase';

    var FIELD_KEYS = {
        heading: 'field_luux_hotel_showcase_heading',
        intro: 'field_luux_hotel_showcase_intro',
        footnote: 'field_luux_hotel_showcase_footnote',
        section_id: 'field_luux_hotel_showcase_section_id',
    };

    var HOTEL_SUB_KEYS = {
        name: 'field_luux_hotel_showcase_hotel_name',
        image: 'field_luux_hotel_showcase_hotel_image',
        description: 'field_luux_hotel_showcase_hotel_description',
        inclusions: 'field_luux_hotel_showcase_hotel_inclusions',
        cta: 'field_luux_hotel_showcase_hotel_cta',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutHotelShowcase !== 'undefined' && luuxLayoutHotelShowcase.ajaxurl) {
            return luuxLayoutHotelShowcase.ajaxurl;
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

    function readInclusions($row) {
        var inclusions = [];
        var $repeater = $row.find('.acf-field[data-key="field_luux_hotel_showcase_hotel_inclusions"], .acf-field[data-name="inclusions"]').first();

        if (!$repeater.length) {
            return inclusions;
        }

        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var repeaterField = acf.getField($repeater);
            var $rows = repeaterField && typeof repeaterField.$rows === 'function'
                ? repeaterField.$rows()
                : $repeater.find('.acf-row').not('.acf-clone');

            $rows.each(function () {
                var $incRow = $(this);
                var $fieldEl = $incRow.find('.acf-field[data-key="field_luux_hotel_showcase_inclusion_text"]').first();

                if (!$fieldEl.length || typeof acf.getField !== 'function') {
                    return;
                }

                var field = acf.getField($fieldEl);

                if (!field || typeof field.val !== 'function') {
                    return;
                }

                var value = field.val();

                if (value !== null && value !== undefined && String(value) !== '') {
                    inclusions.push({
                        text: String(value),
                        field_luux_hotel_showcase_inclusion_text: String(value),
                    });
                }
            });
        }

        return inclusions;
    }

    function readHotels($layout) {
        var hotels = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_hotel_showcase_hotels"], .acf-field[data-name="hotels"]').first();

        if (!$repeater.length) {
            return hotels;
        }

        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var repeaterField = acf.getField($repeater);
            var $rows = repeaterField && typeof repeaterField.$rows === 'function'
                ? repeaterField.$rows()
                : $repeater.find('> .acf-input > .acf-repeater > .acf-table > tbody > .acf-row, > .acf-repeater > .acf-row').not('.acf-clone');

            if (!$rows.length) {
                $rows = $repeater.find('.acf-row').not('.acf-clone').filter(function () {
                    return $(this).closest('.acf-field[data-name="inclusions"]').length === 0;
                });
            }

            $rows.each(function () {
                var $row = $(this);

                // Skip nested inclusion rows if the filter missed them.
                if ($row.closest('.acf-field[data-name="inclusions"]').length) {
                    return;
                }

                var hotel = {};

                $.each(HOTEL_SUB_KEYS, function (name, key) {
                    if (name === 'inclusions') {
                        return;
                    }

                    var $fieldEl = $row.find('.acf-field[data-key="' + key + '"]').first();

                    if (!$fieldEl.length) {
                        return;
                    }

                    if (name === 'cta') {
                        var link = null;
                        var field = acf.getField($fieldEl);

                        if (field && typeof field.val === 'function') {
                            link = normalizeLink(field.val());
                        }

                        if (!link) {
                            link = readLinkFromInputs($fieldEl);
                        }

                        if (link) {
                            hotel.cta = link;
                            hotel.field_luux_hotel_showcase_hotel_cta = link;
                        }

                        return;
                    }

                    var subField = acf.getField($fieldEl);

                    if (!subField || typeof subField.val !== 'function') {
                        return;
                    }

                    var value = subField.val();

                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    if (name === 'image') {
                        var imageId = normalizeAttachmentId(value);

                        if (imageId !== '') {
                            hotel.image = parseInt(imageId, 10);
                            hotel.field_luux_hotel_showcase_hotel_image = hotel.image;
                        }

                        return;
                    }

                    hotel[name] = String(value);
                    hotel['field_luux_hotel_showcase_hotel_' + name] = hotel[name];
                });

                var inclusions = readInclusions($row);

                if (inclusions.length) {
                    hotel.inclusions = inclusions;
                    hotel.field_luux_hotel_showcase_hotel_inclusions = inclusions;
                }

                if (!$.isEmptyObject(hotel)) {
                    hotels.push(hotel);
                }
            });
        }

        if (hotels.length) {
            return hotels;
        }

        // DOM fallback for hotels + nested inclusions + CTA.
        var bucket = {};
        var hotelPattern = /\[(?:field_luux_hotel_showcase_hotels|hotels)\]\[(?:row-)?(\d+)\]\[(?:field_luux_hotel_showcase_hotel_)?(name|image|description|cta|inclusions)\](?:\[(?:row-)?(\d+)\]\[(?:field_luux_hotel_showcase_inclusion_)?(text)\])?(?:\[(url|title|target)\])?/;

        $layout.find('input, textarea').each(function () {
            var match = (this.name || '').match(hotelPattern);

            if (!match) {
                return;
            }

            var idx = match[1];
            var field = match[2];
            var incIdx = match[3];
            var incField = match[4];
            var linkPart = match[5];
            var value = $(this).val();

            if (!bucket[idx]) {
                bucket[idx] = { cta: { url: '', title: '', target: '' }, inclusions: {} };
            }

            if (field === 'cta') {
                if (linkPart) {
                    bucket[idx].cta[linkPart] = String(value || '');
                }
                return;
            }

            if (field === 'inclusions' && incIdx !== undefined && incField === 'text') {
                if (value !== null && value !== undefined && String(value) !== '') {
                    bucket[idx].inclusions[incIdx] = String(value);
                }
                return;
            }

            if (value === null || value === undefined || String(value) === '') {
                return;
            }

            if (field === 'name' || field === 'image' || field === 'description') {
                bucket[idx][field] = value;
            }
        });

        Object.keys(bucket).sort(function (a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).forEach(function (key) {
            var raw = bucket[key];
            var hotel = {};

            if (raw.name) {
                hotel.name = String(raw.name);
                hotel.field_luux_hotel_showcase_hotel_name = hotel.name;
            }

            if (raw.image) {
                var imageId = normalizeAttachmentId(raw.image);

                if (imageId !== '') {
                    hotel.image = parseInt(imageId, 10);
                    hotel.field_luux_hotel_showcase_hotel_image = hotel.image;
                }
            }

            if (raw.description) {
                hotel.description = String(raw.description);
                hotel.field_luux_hotel_showcase_hotel_description = hotel.description;
            }

            var inclusions = [];

            Object.keys(raw.inclusions || {}).sort(function (a, b) {
                return parseInt(a, 10) - parseInt(b, 10);
            }).forEach(function (incKey) {
                inclusions.push({
                    text: raw.inclusions[incKey],
                    field_luux_hotel_showcase_inclusion_text: raw.inclusions[incKey],
                });
            });

            if (inclusions.length) {
                hotel.inclusions = inclusions;
                hotel.field_luux_hotel_showcase_hotel_inclusions = inclusions;
            }

            var link = normalizeLink(raw.cta);

            if (link) {
                hotel.cta = link;
                hotel.field_luux_hotel_showcase_hotel_cta = link;
            }

            if (!$.isEmptyObject(hotel)) {
                hotels.push(hotel);
            }
        });

        return hotels;
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
        var hotels = readHotels($layout);

        if (hotels.length) {
            fields.hotels_json = JSON.stringify(hotels);
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

        $('#luux-hotel-showcase-early').remove();

        var $wrap = $('<div id="luux-hotel-showcase-early" style="display:none" aria-hidden="true"></div>');

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                if (name === 'hotels_json' || name === 'intro') {
                    $('<textarea>', {
                        name: 'luux_hotel_showcase[' + item.domIndex + '][' + name + ']',
                        'aria-hidden': 'true',
                    }).css({ position: 'absolute', left: '-9999px', height: '1px', width: '1px' })
                        .val(value)
                        .appendTo($wrap);
                    return;
                }

                $('<input>', {
                    type: 'hidden',
                    name: 'luux_hotel_showcase[' + item.domIndex + '][' + name + ']',
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
            action: 'luux_save_hotel_showcase_fields',
            nonce: luuxLayoutHotelShowcase.nonce,
            post_id: postId,
            row_index: rowIndex,
            row_nth: rowNth,
            fields: {},
        };

        $.each(fields, function (name, value) {
            if (name === 'hotels_json') {
                payload.hotels_json = value;
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
        return layout === LAYOUT || layout === 'layout_luux_hotel_showcase';
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

        if (fields.hotels_json) {
            try {
                var hotels = JSON.parse(fields.hotels_json);

                if ($.isArray(hotels) && hotels.length) {
                    acfRow.field_luux_hotel_showcase_hotels = hotels;
                    acfRow.hotels = hotels;
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

        return key.indexOf('field_luux_hotel_showcase_') === 0;
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
        if (initialized || typeof luuxLayoutHotelShowcase === 'undefined') {
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
