/**
 * Video Tours admin save helpers.
 * Large resort pages (Ikos) can exceed max_input_vars — video fields at the end of the
 * form are dropped from $_POST. We inject early hidden fields and AJAX-save on change.
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

    function getFlexibleLayouts($flex) {
        var layouts = [];

        $flex.find('> .values > .layout').each(function (index) {
            var $layout = $(this);
            layouts.push({
                index: index,
                layout: $layout.attr('data-layout') || '',
                $el: $layout,
            });
        });

        return layouts;
    }

    function readFieldValue($layout, name, key) {
        var $field = $layout.find('.acf-field[data-key="' + key + '"]');

        if (!$field.length) {
            return '';
        }

        if (name.indexOf('media_type_') === 0) {
            var $checked = $field.find('input[type="radio"]:checked');
            return $checked.length ? $checked.val() : '';
        }

        var $hidden = $field.find('input[type="hidden"]').filter(function () {
            return this.name && this.name.indexOf(key) !== -1;
        });

        if ($hidden.length) {
            return $hidden.first().val() || '';
        }

        return '';
    }

    function readRowFields($layout) {
        var fields = {};

        $.each(FIELD_KEYS, function (name, key) {
            var value = readFieldValue($layout, name, key);
            if (value !== '') {
                fields[name] = value;
            }
        });

        return fields;
    }

    function injectEarlyFields() {
        var $form = $('#post');

        if (!$form.length) {
            return;
        }

        $('#luux-vt-early').remove();

        var $wrap = $('<div id="luux-vt-early" style="display:none" aria-hidden="true"></div>');
        var $flex = $('.acf-field[data-name="page_sections"] .acf-flexible-content').first();

        if (!$flex.length) {
            return;
        }

        getFlexibleLayouts($flex).forEach(function (item) {
            if (item.layout !== 'video_tours') {
                return;
            }

            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                $('<input>', {
                    type: 'hidden',
                    name: 'luux_video_tours[' + item.index + '][' + name + ']',
                    value: value,
                }).appendTo($wrap);
            });
        });

        if ($wrap.children().length) {
            $form.prepend($wrap);
        }
    }

    function saveRowAjax(rowIndex, fields) {
        var postId = $('#post_ID').val();

        if (!postId || !fields || $.isEmptyObject(fields)) {
            return;
        }

        $.post(ajaxurl, {
            action: 'luux_save_video_tours_media',
            nonce: luuxVideoTours.nonce,
            post_id: postId,
            row_index: rowIndex,
            fields: fields,
        });
    }

    function layoutIndexForField(field) {
        var $layout = field.$el.closest('.layout');

        if (!$layout.length) {
            return -1;
        }

        var $flex = $layout.closest('.acf-flexible-content');

        if (!$flex.length) {
            return -1;
        }

        return $flex.find('> .values > .layout').index($layout);
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

    $('#post').on('submit', injectEarlyFields);

    acf.addAction('change', function (field) {
        if (!isVideoToursField(field) || !isPageSectionsField(field)) {
            return;
        }

        var rowIndex = layoutIndexForField(field);

        if (rowIndex < 0) {
            return;
        }

        var $layout = field.$el.closest('.layout');
        var fields = readRowFields($layout);

        saveRowAjax(rowIndex, fields);
    });
})(jQuery);
