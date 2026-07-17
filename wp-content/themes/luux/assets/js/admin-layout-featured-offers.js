/**
 * Featured Offers layout admin save helpers.
 * Block editor + legacy imports can drop fields from the save payload.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'featured_offers';

    var FIELD_KEYS = {
        section_label: 'field_luux_featured_offers_section_label',
        heading: 'field_luux_featured_offers_heading',
        intro: 'field_luux_featured_offers_intro',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutFeaturedOffers !== 'undefined' && luuxLayoutFeaturedOffers.ajaxurl) {
            return luuxLayoutFeaturedOffers.ajaxurl;
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
        var layouts = [];
        var layoutNth = 0;

        $flex.find('> .values > .layout').each(function (domIndex) {
            var $layout = $(this);
            var layout = $layout.attr('data-layout') || '';
            var item = {
                domIndex: domIndex,
                rowNth: layout === LAYOUT ? layoutNth : -1,
                layout: layout,
                $el: $layout,
            };

            if (layout === LAYOUT) {
                layoutNth += 1;
            }

            layouts.push(item);
        });

        return layouts;
    }

    function fieldNameForKey(key) {
        var name = '';

        $.each(FIELD_KEYS, function (fieldName, fieldKey) {
            if (fieldKey === key) {
                name = fieldName;
            }
        });

        return name;
    }

    function readFieldValueFromAcf($fieldEl) {
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

        if (typeof value === 'object' && value.id) {
            return String(value.id);
        }

        if (typeof value === 'object' && value.ID) {
            return String(value.ID);
        }

        return String(value);
    }

    function readRowFields($layout) {
        var fields = {};

        $layout.find('.acf-field').each(function () {
            var $fieldEl = $(this);
            var key = $fieldEl.attr('data-key') || '';
            var name = fieldNameForKey(key);

            if (!name) {
                return;
            }

            var value = readFieldValueFromAcf($fieldEl);

            if (value !== '') {
                fields[name] = value;
            }
        });

        return fields;
    }

    function readRowWithOffers($layout) {
        var row = readRowFields($layout);
        var offers = readOffersRepeater($layout);

        if (offers.length) {
            row.offers = offers;
        }

        return row;
    }

    function readOffersRepeater($layout) {
        var offers = [];
        var $repeater = $layout.find('.acf-field[data-key="field_luux_featured_offers_offers"]');

        if (!$repeater.length || typeof acf === 'undefined' || typeof acf.getField !== 'function') {
            return offers;
        }

        var repeaterField = acf.getField($repeater);

        if (!repeaterField || typeof repeaterField.val !== 'function') {
            return offers;
        }

        var value = repeaterField.val();

        if (!value || !$.isArray(value)) {
            return offers;
        }

        value.forEach(function (item) {
            if (!item || typeof item !== 'object') {
                return;
            }

            var offer = {};

            if (item.field_luux_featured_offers_image || item.image) {
                offer.field_luux_featured_offers_image = item.field_luux_featured_offers_image || item.image;
            }

            if (item.field_luux_featured_offers_title || item.title) {
                offer.field_luux_featured_offers_title = item.field_luux_featured_offers_title || item.title;
            }

            if (item.field_luux_featured_offers_description || item.description) {
                offer.field_luux_featured_offers_description = item.field_luux_featured_offers_description || item.description;
            }

            if (item.field_luux_featured_offers_price || item.price) {
                offer.field_luux_featured_offers_price = item.field_luux_featured_offers_price || item.price;
            }

            if (item.field_luux_featured_offers_link || item.link) {
                offer.field_luux_featured_offers_link = item.field_luux_featured_offers_link || item.link;
            }

            if (!$.isEmptyObject(offer)) {
                offers.push(offer);
            }
        });

        return offers;
    }

    function getLayoutRows() {
        var $flex = getPageSectionsFlex();

        if (!$flex.length) {
            return [];
        }

        return getFlexibleLayouts($flex).filter(function (item) {
            return item.layout === LAYOUT;
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

        $('#luux-featured-offers-early').remove();

        var $wrap = $('<div id="luux-featured-offers-early" style="display:none" aria-hidden="true"></div>');
        var rows = getLayoutRows();

        rows.forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                $('<input>', {
                    type: 'hidden',
                    name: 'luux_featured_offers[' + item.domIndex + '][' + name + ']',
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

        return $.ajax({
            url: url,
            method: 'POST',
            async: async !== false,
            data: {
                action: 'luux_save_featured_offers_fields',
                nonce: luuxLayoutFeaturedOffers.nonce,
                post_id: postId,
                row_index: rowIndex,
                row_nth: rowNth,
                fields: fields,
            },
        });
    }

    function stashAllRows(async) {
        if (savingRows) {
            return $.Deferred().resolve().promise();
        }

        savingRows = true;

        var rows = getLayoutRows();
        var chain = $.Deferred().resolve().promise();

        rows.forEach(function (item) {
            var fields = readRowWithOffers(item.$el);

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

        if (fields.offers && $.isArray(fields.offers) && fields.offers.length) {
            acfRow.field_luux_featured_offers_offers = fields.offers;
            acfRow.offers = fields.offers;
        }

        return acfRow;
    }

    function layoutMatches(layout) {
        return layout === LAYOUT || layout === 'layout_luux_featured_offers';
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

        var nth = 0;

        $.each(fc, function (key, row) {
            if (!row || typeof row !== 'object') {
                return;
            }

            if (!layoutMatches(row.acf_fc_layout || '')) {
                return;
            }

            var domRow = rows[nth];

            if (domRow) {
                fc[key] = mergeRowIntoAcfPayload(readRowWithOffers(domRow.$el), row);
            }

            nth += 1;
        });

        data.meta.acf[fcKey] = fc;
        data.acf[fcKey] = fc;

        return data;
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

    function mergeDomIntoAcfData(data) {
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

        var rows = getLayoutRows();
        var nth = 0;

        $.each(fc, function (key, row) {
            if (!row || typeof row !== 'object') {
                return;
            }

            if (!layoutMatches(row.acf_fc_layout || '')) {
                return;
            }

            var domRow = rows[nth];

            if (domRow) {
                fc[key] = mergeRowIntoAcfPayload(readRowWithOffers(domRow.$el), row);
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

        return key.indexOf('field_luux_featured_offers_') === 0;
    }

    function handleFieldChange(field) {
        if (!isLayoutField(field)) {
            return;
        }

        var indices = layoutIndexForField(field);

        if (indices.domIndex < 0) {
            return;
        }

        var $layout = field.$el.closest('.layout');
        var fields = readRowWithOffers($layout);

        saveRowAjax(indices.domIndex, indices.rowNth, fields, true);
    }

    function beforeSave() {
        injectEarlyFields();
        return stashAllRows(false);
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
        if (initialized || typeof luuxLayoutFeaturedOffers === 'undefined') {
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
