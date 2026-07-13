/**
 * Video Tours admin save helpers.
 * Block editor + large resort pages can drop video fields from the save payload.
 */
(function ($) {
    'use strict';

    var FIELD_KEYS = {
        media_type_left: 'field_luux_video_tours_media_type_left',
        media_type_right: 'field_luux_video_tours_media_type_right',
        image_left: 'field_luux_video_tours_image_left',
        image_right: 'field_luux_video_tours_image_right',
        video_left: 'field_luux_video_tours_video_left',
        video_right: 'field_luux_video_tours_video_right',
    };

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxVideoTours !== 'undefined' && luuxVideoTours.ajaxurl) {
            return luuxVideoTours.ajaxurl;
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
        var videoToursNth = 0;

        $flex.find('> .values > .layout').each(function (domIndex) {
            var $layout = $(this);
            var layout = $layout.attr('data-layout') || '';
            var item = {
                domIndex: domIndex,
                rowNth: layout === 'video_tours' ? videoToursNth : -1,
                layout: layout,
                $el: $layout,
            };

            if (layout === 'video_tours') {
                videoToursNth += 1;
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

        var mediaTypeLeft = fields.media_type_left || 'image';
        var mediaTypeRight = fields.media_type_right || 'image';

        if (mediaTypeLeft === 'video') {
            delete fields.image_left;
        } else {
            delete fields.video_left;
        }

        if (mediaTypeRight === 'video') {
            delete fields.image_right;
        } else {
            delete fields.video_right;
        }

        if (fields.video_left && !fields.media_type_left) {
            fields.media_type_left = 'video';
        }

        if (fields.video_right && !fields.media_type_right) {
            fields.media_type_right = 'video';
        }

        return fields;
    }

    function getVideoToursRows() {
        var $flex = getPageSectionsFlex();

        if (!$flex.length) {
            return [];
        }

        return getFlexibleLayouts($flex).filter(function (item) {
            return item.layout === 'video_tours';
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

        $('#luux-vt-early').remove();

        var $wrap = $('<div id="luux-vt-early" style="display:none" aria-hidden="true"></div>');
        var rows = getVideoToursRows();

        rows.forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                $('<input>', {
                    type: 'hidden',
                    name: 'luux_video_tours[' + item.domIndex + '][' + name + ']',
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
                action: 'luux_save_video_tours_media',
                nonce: luuxVideoTours.nonce,
                post_id: postId,
                row_index: rowIndex,
                row_nth: rowNth,
                fields: fields,
            },
        });
    }

    function stashAllVideoToursRows(async) {
        if (savingRows) {
            return $.Deferred().resolve().promise();
        }

        savingRows = true;

        var rows = getVideoToursRows();
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
            acfRow = { acf_fc_layout: 'video_tours' };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (fields[name]) {
                acfRow[key] = fields[name];
                acfRow[name] = fields[name];
            }
        });

        return acfRow;
    }

    function mergeDomVideoToursIntoAcfData(data) {
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

        var rows = getVideoToursRows();
        var nth = 0;

        $.each(fc, function (key, row) {
            if (!row || typeof row !== 'object') {
                return;
            }

            var layout = row.acf_fc_layout || '';

            if (layout !== 'video_tours' && layout !== 'layout_luux_video_tours') {
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
            if ($(this).attr('data-layout') === 'video_tours') {
                if (index === domIndex) {
                    rowNth = count;
                }
                count += 1;
            }
        });

        return { domIndex: domIndex, rowNth: rowNth };
    }

    function isVideoToursField(field) {
        if (!field || typeof field.get !== 'function') {
            return false;
        }

        return Object.values(FIELD_KEYS).indexOf(field.get('key') || '') !== -1;
    }

    function handleFieldChange(field) {
        if (!isVideoToursField(field)) {
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
        return stashAllVideoToursRows(false);
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
        if (initialized || typeof luuxVideoTours === 'undefined') {
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
                return mergeDomVideoToursIntoAcfData(data);
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
