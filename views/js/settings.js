jQuery(function ($) {

    $(document).ready(function () {
        var $panel = $('#settings-panel');
        $panel.prepend($('.settings-error'));

        var $form = $(form_id);
        $form.find('h2').each(function (idx) {
            $(this).find('hr').remove();
            $(this).before($('<hr/>'));
            var title = $(this).text().trim();
            $(this).attr('class', 'setting-closed tooltip').attr('title', 'Click to toggle - ' + title);
            if (section === '' || !title.startsWith(section)) {
                $(this).next().toggle();
            }
            $(this).on('click', function () {
                $(this).toggleClass('setting-open');
                $(this).next().toggle();
            })
        });

        $('#export_settings').on('click', function (e) {
            swal({
                title: 'Are you sure?',
                html: 'Are you sure you want to backup and export settings?',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes!',
            }).then(function (result) {
                if (result) {
                    $.fn.working();
                    var paramObj = {};
                    paramObj.page = page;
                    $.fn.action(siteurl + "/?" + $.param(paramObj), 'Settings', 10, function (status) {
                        if (status == -1) {
                            $.fn.failed();
                        } else {
                            swal({
                                title: "Cool!",
                                html: '<br/>That worked!',
                                type: "success",
                                showCancelButton: false,
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            }).catch(swal.noop);
            return false;
        });

        $('#import_settings').on('click', function (e) {
            swal({
                title: 'Import Settings',
                html:
                    '<form enctype="multipart/form-data" method="post" action="' + siteurl + '/?page=' + page + '_import" name="file-import-settings-form" id="file-import-settings-form">' +
                    '<input type="hidden" id="_wpnonce" name="_wpnonce" value="' + nonce + '">' +
                    '<input type="hidden" name="upload-marker"  value="' + uploadmarker + '">' +
                    '<input type="file" name="upload-file" id="settings-file" class="inputfile">' +
                    '<label for="settings-file"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="17" viewBox="0 0 20 17"><path d="M10 0l-5.2 4.9h3.3v5.1h3.8v-5.1h3.3l-5.2-4.9zm9.3 11.5l-3.2-2.1h-2l3.4 2.6h-3.5c-.1 0-.2.1-.2.1l-.8 2.3h-6l-.8-2.2c-.1-.1-.1-.2-.2-.2h-3.6l3.4-2.6h-2l-3.2 2.1c-.4.3-.7 1-.6 1.5l.6 3.1c.1.5.7.9 1.2.9h16.3c.6 0 1.1-.4 1.3-.9l.6-3.1c.1-.5-.2-1.2-.7-1.5z"/></svg> <span>Choose a file&hellip;</span></label>' +
                    '</form>',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Import!',
                onOpen: function (el) {
                    $('#settings-file').on('change', function (e) {
                        var fileName = e.target.value.split('\\').pop();
                        $label = $(this).next('label').find('span').text(fileName);
                    });
                }
            }).then(function (result) {
                if (result) {
                    var f = $("#file-import-settings-form input[name=upload-file]").val();
                    if ($.trim(f).length === 0) {
                        return false;
                    } else {
                        var fExt = f.split('.').pop().toLowerCase();
                        if (fExt === 'txt') {
                            $('#file-import-settings-form').submit();
                            $.fn.working();
                        } else {
                            $.fn.failed('Wrong file extension, must be .txt not .' + fExt);
                        }
                    }
                }
            }).catch(swal.noop);
            return false;
        });

        $('#restore_settings').on('click', function (e) {
            swal({
                title: 'Are you sure?',
                html: 'Are you sure you want to restore settings?',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes!'
            }).then(function (result) {
                if (result) {
                    $.fn.working();
                    var paramObj = {};
                    paramObj.page = page + '_restore';
                    $.fn.action(siteurl + "/?" + $.param(paramObj), 'Settings', 10, function (status) {
                        if (status == -1) {
                            $.fn.failed();
                        } else {
                            swal({
                                title: "Cool!",
                                html: '<br/>That worked!',
                                type: "success",
                                showCancelButton: false,
                                confirmButtonText: 'OK'
                            }).then(function (result) {
                                location = adminurl;
                            });
                        }
                    });
                }
            }).catch(swal.noop);
            return false;
        });

        $('.help').each(function (i, o) {
            $(this).click(function (e) {
                $(this).next().toggle();
            });
        });

        $('.hobo-switch-input').each(function (i, o) {
            var $div = $(this).next('div');
            $(this).change(function (e) {
                if ($(this).prop('checked')) {
                    $div.addClass('-on');
                } else {
                    $div.removeClass('-on');
                }
            });
        });

        $panel.show();

    });

});
