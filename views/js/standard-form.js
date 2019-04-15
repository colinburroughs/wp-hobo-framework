jQuery(function ($) {
    $(document).ready(function () {

        var $form = $(form_id);
        var $update = $(update_button_id);
        var $reset = $(reset_button_id);
        var $reload = $(reload_button_id);

        var original_data = $form.serialize();
        var page_updated = false;
        $.fn.disable = function (bool) {
            $update.prop('disabled', bool);
            $reset.prop('disabled', bool);
            $reload.prop('disabled', bool);
            if (bool) {
                clearInterval(page_updated);
                page_updated = false;
            } else {
                if (!page_updated) {
                    page_updated = setInterval(function () {
                        $update.fadeTo(250, .1).fadeTo(250, 1);
                    }, 500);
                }
            }
        };

        $.fn.edited = function (force) {
            if ($form.serialize() !== original_data || force === true) {
                $.fn.disable(false);
            } else {
                $.fn.disable(true);
            }
        };

        $form.find(':input').on('keyup change', function () {
            $.fn.edited();
        });

        $reset.on('click', function () {
            swal({
                title: 'Are you sure?',
                html: 'Are you sure you want to reset the values?',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes!',
            }).then(function (result) {
                if (result) {
                    $.fn.disable(true);
                    $form[0].reset();
                    // Reset hidden form fields.
                    var formFields = decodeURIComponent(original_data).split('&');
                    var splitFields = [];
                    for (var i in formFields) {
                        var val = formFields[i].split(/=(.*)/);
                        splitFields[val[0]] = val[1];
                    }
                    $form.find('input[type=hidden]').each(function (i, o) {
                        this.value = splitFields[this.name];
                    });
                    // Reset markers, eg images.
                    $('.settings-reset-marker').each(function (i, o) {
                        if ($(this).hasClass('slider')) {
                            val = $(this).attr('initial');
                            $(this).slider('value', val);
                            $.fn.sliderMove($(this), val);
                        } else if ($(this).hasClass('color-picker')) {
                            $(this).trigger('change');
                        } else if ($(this).hasClass('hobo-switch-input')) {
                            $(this).trigger('change');
                        } else {
                            $(this).attr('src', $(this).attr('data-src'));
                        }
                    });
                    if (typeof $.fn.resetCallback !== 'undefined') {
                        $.fn.resetCallback();
                    }
                    $.fn.disable(true);
                }
            });//.catch(swal.noop);
            return false;
        });

        $update.on('click', function () {
            swal({
                title: 'Are you sure?',
                html: 'Are you sure you want to submit your updates?',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes!',
            }).then(function (result) {
                if (result) {
                    $.fn.working();
                    $form.submit();
                }
            }).catch(swal.noop);
            return false;
        });

        $reload.on('click', function () {
            swal({
                title: 'Are you sure?',
                html: 'Are you sure you want to reload?',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes!',
            }).then(function (result) {
                if (result) {
                    $.fn.working();
                    var $form = $('<form/>').attr('method', 'post').attr('action', $('#wp-admin-canonical').attr('href'));
                    $('#wpbody').append($form);
                    $form.submit();
                }
            }).catch(swal.noop);
            return false;
        });

        if (typeof tinymce !== 'undefined') {
            tinymce.PluginManager.add('keyup_event', function (editor, url) {
                editor.on('keyup', function (e) {
                    // Ignore arrow and page keyboard key codes]
                    if ($.inArray(e.keyCode, [33, 34, 37, 38, 39, 40]) === -1) {
                        $.fn.edited(true);
                    }
                });
            });
        }
    });
});
