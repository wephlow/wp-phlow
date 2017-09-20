jQuery(function($) {

    function widgetInit() {
        var widget = this;

        var inputEmail = $('.phlow-reg-email', widget),
            inputPasswd = $('.phlow-reg-passwd', widget),
            btnSubmit = $('.phlow-reg-submit', widget),
            boxErrors = $('.phlow-reg-errors', widget),
            loader = $('.phlow-reg-loader', widget),
            isBusy = false;

        btnSubmit.on('click', function() {
            if (isBusy) {
                return;
            }

            isBusy = true;
            loader.show();
            btnSubmit.hide();
            boxErrors.empty().hide();

            var req = $.ajax({
                method: 'POST',
                url: phlowAjax.url,
                dataType: 'json',
                data: {
                    action: 'phlow_user_create',
                    email: inputEmail.val(),
                    password: inputPasswd.val()
                }
            });

            req.done(function(res) {
                if (!res.success) {
                    res.errors.forEach(function(value) {
                        var item = $('<li />').text(value);
                        boxErrors.append(item);
                    });

                    boxErrors.show();
                    return;
                }

                var boxSuccess = $('<div />')
                    .addClass('phlow-reg-success')
                    .text('Thanks for registering to phlow. Please check your email for the next step');

                widget.html(boxSuccess);
            });

            req.fail(function(err) {
                console.error(err);
            });

            req.always(function() {
                isBusy = false;
                loader.hide();
                btnSubmit.show();
            });
        });
    }

    function widgetsInit() {
        var widgets = $('div[id*=phlow_registration]');

        if (!widgets.length) {
            return;
        }

        widgets.each(function() {
            var widget = $(this);
            widgetInit.call(widget);
        });
    }

    widgetsInit();
});
