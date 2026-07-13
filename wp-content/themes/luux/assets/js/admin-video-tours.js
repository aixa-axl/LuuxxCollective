/**
 * Video Tours admin save helpers.
 * Block editor + large resort pages can drop video fields from the save payload.
 * We stash values via AJAX and inject DOM state before ACF serializes.
 */
(function ($) {
    'use strict';

    if (typeof acf === 'undefined' || typeof luuxVideoTours === 'undefined') {
        return;
    }

    var FIELD_KEYS = {
        media_type_left: 'field_luux_video_tours_media_type_left',
        media_type_right: 'field_luux_video_tours_media_type_right',
        image_left: 'field_luux_video_tours_image_left',
        image_right: 'field_luux_video_tours_image_right',
        video_left: 'field_luux_video_tours_video_left',
        video_right: 'field_luux_video_tours_video_right',
    };

    var FIELD_NAMES = Object.keys(FIELD_KEYS);
    var savingRows = false;

    function getPageSectionsFlex() {
        return $('.acf-field[data-name="page_sections"] .acf-flexible-content').first();
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

    function readMediaTypeValue($field) {
        var $checked = $field.find('input[type="radio"]:checked');

        if ($checked.length) {
            return $checked.val() || '';
        }

        var $select = $field.find('select').first();

        if ($select.length) {
            return $select.val() || '';
        }

        return '';
    }

    function readAttachmentValue($field) {
        var $hidden = $field.find('input[type="hidden"]').filter(function () {
            return this.name && !/^acf\[[^\]]+\]$/.test(this.name);
        });

        if ($hidden.length) {
            var val = $hidden.first().val();
            if (val) {
                return val;
            }
        }

        $hidden = $field.find('.acf-file-uploader input[type="hidden"], .acf-image-uploader input[type="hidden"]');

        if ($hidden.length) {
            var attachmentVal = $hidden.first().val();
            if (attachmentVal) {
                return attachmentVal;
            }
        }

        var dataId = $field.find('[data-id]').first().attr('data-id');

        if (dataId) {
            return dataId;
        }

        return '';
    }

    function readFieldValue($layout, name, key) {
        var $field = $layout.find('.acf-field[data-key="' + key + '"]');

        if (!$field.length) {
            return '';
        }

        if (name.indexOf('media_type_') === 0) {
            return readMediaTypeValue($field);
        }

        return readAttachmentValue($field);
    }

    function readRowFields($layout) {
        var fields = {};
        var mediaTypeLeft = readFieldValue($layout, 'media_type_left', FIELD_KEYS.media_type_left) || 'image';
        var mediaTypeRight = readFieldValue($layout, 'media_type_right', FIELD_KEYS.media_type_right) || 'image';

        $.each(FIELD_KEYS, function (name, key) {
            if (name === 'image_left' && mediaTypeLeft === 'video') {
                return;
            }

            if (name === 'video_left' && mediaTypeLeft === 'image') {
                return;
            }

            if (name === 'image_right' && mediaTypeRight === 'video') {
                return;
            }

            if (name === 'video_right' && mediaTypeRight === 'image') {
                return;
            }

            var value = readFieldValue($layout, name, key);

            if (value !== '') {
                fields[name] = value;
            }
        });

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
            $form = $('.acf-postbox').closest('form');

            if (!$form.length) {
                $form = $('form#post');
            }
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

        if (!postId || !fields || $.isEmptyObject(fields)) {
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: ajaxurl,
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

    function mergeRowIntoAcfPayload(row, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: 'video_tours' };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (row.fields[name]) {
                acfRow[key] = row.fields[name];
                acfRow[name] = row.fields[name];
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
                fc[key] = mergeRowIntoAcfPayload({ fields: readRowFields(domRow.$el) }, row);
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
        var key = field.get('key') || '';

        return Object.values(FIELD_KEYS).indexOf(key) !== -1;
    }

    function isPageSectionsField(field) {
        var parent = field.parent();

        while (parent) {
            if (parent.get('name') === 'page_sections') {
                return true;
            }
            parent = parent.parent();
        }

        return false;
    }

    function beforeSave() {
        injectEarlyFields();
        return stashAllVideoToursRows(false);
    }

    $('#post').on('submit', function () {
        beforeSave();
    });

    acf.addAction('submit', function () {
        beforeSave();
    });

    acf.addAction('prepare', function () {
        injectEarlyFields();
    });

    if (acf.addFilter) {
        acf.addFilter('prepare_for_ajax', function (data) {
            injectEarlyFields();
            return mergeDomVideoToursIntoAcfData(data);
        });
    }

    acf.addAction('change', function (field) {
        if (!isVideoToursField(field) || !isPageSectionsField(field)) {
            return;
        }

        var indices = layoutIndexForField(field);

        if (indices.domIndex < 0) {
            return;
        }

        var $layout = field.$el.closest('.layout');
        var fields = readRowFields($layout);

        saveRowAjax(indices.domIndex, indices.rowNth, fields, true);
    });

    if (window.wp && wp.data && wp.data.subscribe && wp.data.select) {
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
})(jQuery);
