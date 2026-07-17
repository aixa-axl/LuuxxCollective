/**
 * Contact Options layout admin save helpers.
 */
(function ($) {
    'use strict';

    var LAYOUT = 'contact_options';

    var FIELD_KEYS = {
        heading: 'field_luux_contact_options_heading',
        subheading: 'field_luux_contact_options_subheading',
        whatsapp_title: 'field_luux_contact_options_whatsapp_title',
        whatsapp_text: 'field_luux_contact_options_whatsapp_text',
        whatsapp_link: 'field_luux_contact_options_whatsapp_link',
        call_title: 'field_luux_contact_options_call_title',
        call_text: 'field_luux_contact_options_call_text',
        call_phone: 'field_luux_contact_options_call_phone',
        call_button_label: 'field_luux_contact_options_call_button_label',
        form_title: 'field_luux_contact_options_form_title',
        form_text: 'field_luux_contact_options_form_text',
        form_id: 'field_luux_contact_options_form_id',
        section_id: 'field_luux_contact_options_section_id',
    };

    var TEXTAREA_FIELDS = ['subheading', 'whatsapp_text', 'call_text', 'form_text'];

    var TOP_LEVEL_PAYLOAD_KEYS = [
        'heading',
        'subheading',
        'whatsapp_title',
        'whatsapp_text',
        'whatsapp_link_json',
        'call_title',
        'call_text',
        'call_phone',
        'call_button_label',
        'form_title',
        'form_text',
        'form_id',
        'section_id',
    ];

    var savingRows = false;
    var initialized = false;

    function getAjaxUrl() {
        if (typeof luuxLayoutContactOptions !== 'undefined' && luuxLayoutContactOptions.ajaxurl) {
            return luuxLayoutContactOptions.ajaxurl;
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

    function readScalarField($fieldEl, name) {
        if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
            var field = acf.getField($fieldEl);

            if (field && typeof field.val === 'function') {
                var value = field.val();

                if (value !== null && value !== undefined && value !== '') {
                    return String(value);
                }
            }
        }

        var selector = TEXTAREA_FIELDS.indexOf(name) !== -1
            ? 'textarea'
            : 'input[type="text"], textarea';

        var $input = $fieldEl.find(selector).first();

        if ($input.length && $input.val()) {
            return String($input.val());
        }

        return null;
    }

    function readRowFields($layout) {
        expandLayout($layout);

        var fields = {};

        $.each(FIELD_KEYS, function (name, key) {
            var $fieldEl = $layout.find('.acf-field[data-key="' + key + '"]').first();

            if (!$fieldEl.length) {
                $fieldEl = $layout.find('.acf-field[data-name="' + name + '"]').first();
            }

            if (!$fieldEl.length) {
                return;
            }

            if (name === 'whatsapp_link') {
                var link = null;

                if (typeof acf !== 'undefined' && typeof acf.getField === 'function') {
                    var linkField = acf.getField($fieldEl);

                    if (linkField && typeof linkField.val === 'function') {
                        link = normalizeLink(linkField.val());
                    }
                }

                if (!link) {
                    link = readLinkFromInputs($fieldEl);
                }

                if (link) {
                    fields.whatsapp_link_json = JSON.stringify(link);
                }

                return;
            }

            var scalar = readScalarField($fieldEl, name);

            if (scalar) {
                fields[name] = scalar;
            }
        });

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

        $('#luux-contact-options-early').remove();

        var $wrap = $('<div id="luux-contact-options-early" style="display:none" aria-hidden="true"></div>');

        getLayoutRows().forEach(function (item) {
            var fields = readRowFields(item.$el);

            $.each(fields, function (name, value) {
                if (name.indexOf('_json') !== -1) {
                    $('<textarea>', {
                        name: 'luux_contact_options[' + item.domIndex + '][' + name + ']',
                        'aria-hidden': 'true',
                    }).css({ position: 'absolute', left: '-9999px', height: '1px', width: '1px' })
                        .val(value)
                        .appendTo($wrap);
                    return;
                }

                $('<input>', {
                    type: 'hidden',
                    name: 'luux_contact_options[' + item.domIndex + '][' + name + ']',
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
            action: 'luux_save_contact_options_fields',
            nonce: luuxLayoutContactOptions.nonce,
            post_id: postId,
            row_index: rowIndex,
            row_nth: rowNth,
            fields: {},
        };

        $.each(fields, function (name, value) {
            if (TOP_LEVEL_PAYLOAD_KEYS.indexOf(name) !== -1) {
                payload[name] = value;
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
        return layout === LAYOUT || layout === 'layout_luux_contact_options';
    }

    function mergeRowIntoAcfPayload(fields, acfRow) {
        if (!acfRow || typeof acfRow !== 'object') {
            acfRow = { acf_fc_layout: LAYOUT };
        }

        $.each(FIELD_KEYS, function (name, key) {
            if (name === 'whatsapp_link') {
                if (fields.whatsapp_link_json) {
                    try {
                        var link = JSON.parse(fields.whatsapp_link_json);

                        if (link && link.url) {
                            acfRow[key] = link;
                            acfRow[name] = link;
                        }
                    } catch (e) {
                        // Ignore.
                    }
                }

                return;
            }

            if (fields[name]) {
                acfRow[key] = fields[name];
                acfRow[name] = fields[name];
            }
        });

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

        return Object.values(FIELD_KEYS).indexOf(field.get('key') || '') !== -1;
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
        if (initialized || typeof luuxLayoutContactOptions === 'undefined') {
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
