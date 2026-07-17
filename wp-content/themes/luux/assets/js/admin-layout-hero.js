/**
 * Hero layout admin save helpers.
 * Block editor + legacy imports can drop hero fields from the save payload.
 */
(function ($) {
    'use strict';

    var FIELD_KEYS = {
        heading: 'field_luux_hero_heading',
        subheading: 'field_luux_hero_subheading',
        media_type: 'field_luux_hero_media_type',
        background_image: 'field_luux_hero_background_image',
        background_video: 'field_luux_hero_background_video',
        show_group_tag: 'field_luux_hero_show_group_tag',
        group_tag_logo: 'field_luux_hero_group_tag_logo',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutHero !== 'undefined' && luuxLayoutHero.ajaxurl) {
            return luuxLayoutHero.ajaxurl;
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
        var heroNth = 0;

        $flex.find('> .values > .layout').each(function (domIndex) {
            var $layout = $(this);
            var layout = $layout.attr('data-layout') || '';
            var item = {
                domIndex: domIndex,
                rowNth: layout === 'hero' ? heroNth : -1,
                layout: layout,
                $el: $layout,
            };

            if (layout === 'hero') {
                heroNth += 1;
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

        if (typeof value === 'boolean') {
            return value ? '1' : '0';
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

        var mediaType = fields.media_type || 'image';

        if (mediaType === 'video') {
            delete fields.background_image;
        } else {
            delete fields.background_video;
        }

        return fields;
    }

    function getHeroRows() {
        var $flex = getPageSectionsFlex();

        if (!$flex.length) {
            return [];
        }

        return getFlexibleLayouts($flex).filter(function (item) {
            return item.layout === 'hero';
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

        $('#luux-hero-early').remove();

        var $wrap = $('<div id="luux-hero-early" style="display:none" aria-hidden="true"></div>');
        var rows = getHeroRows();

        rows.forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                $('<input>', {
                    type: 'hidden',
                    name: 'luux_hero[' + item.domIndex + '][' + name + ']',
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
                action: 'luux_save_hero_fields',
                nonce: luuxLayoutHero.nonce,
                post_id: postId,
                row_index: rowIndex,
                row_nth: rowNth,
                fields: fields,
            },
        });
    }

    function stashAllHeroRows(async) {
        if (savingRows) {
            return $.Deferred().resolve().promise();
        }

        savingRows = true;

        var rows = getHeroRows();
        var chain = $.Deferred().resolve().promise();

        rows.forEach(function (item) {
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

    function mergeRowIntoAcfPayload(fields, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: 'hero' };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (fields[name]) {
                acfRow[key] = fields[name];
                acfRow[name] = fields[name];
            }
        });

        return acfRow;
    }

    function mergeDomHeroIntoAcfData(data) {
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

        var rows = getHeroRows();
        var nth = 0;

        $.each(fc, function (key, row) {
            if (!row || typeof row !== 'object') {
                return;
            }

            var layout = row.acf_fc_layout || '';

            if (layout !== 'hero' && layout !== 'layout_luux_hero') {
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
            if ($(this).attr('data-layout') === 'hero') {
                if (index === domIndex) {
                    rowNth = count;
                }
                count += 1;
            }
        });

        return { domIndex: domIndex, rowNth: rowNth };
    }

    function isHeroField(field) {
        if (!field || typeof field.get !== 'function') {
            return false;
        }

        return Object.values(FIELD_KEYS).indexOf(field.get('key') || '') !== -1;
    }

    function handleFieldChange(field) {
        if (!isHeroField(field)) {
            return;
        }

        var indices = layoutIndexForField(field);

        if (indices.domIndex < 0) {
            return;
        }

        var $layout = field.$el.closest('.layout');
        var fields = readRowFields($layout);

        saveRowAjax(indices.domIndex, indices.rowNth, fields, true);
    }

    function beforeSave() {
        injectEarlyFields();
        return stashAllHeroRows(false);
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
        if (initialized || typeof luuxLayoutHero === 'undefined') {
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
                return mergeDomHeroIntoAcfData(data);
            });
        }

        bindBlockEditorSave();
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
