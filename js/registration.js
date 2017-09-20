jQuery(function($) {

    var config = {
        facebook: {
            appId: '1028666243811785',
            scope: 'email, user_about_me, user_birthday, user_website, public_profile'
        },
        google: {
            clientId: '732177001013-7ksob7m2fse0iuoeubhers0vkruou9na.apps.googleusercontent.com',
            cookiePolicy: 'single_host_origin'
        }
    };

    function widgetInit() {
        var widget = this;

        var inputEmail = $('.phlow-reg-email', widget),
            inputPasswd = $('.phlow-reg-passwd', widget),
            boxButtons = $('.phlow-reg-buttons', widget),
            btnSubmit = $('.phlow-reg-submit', widget),
            btnFacebook = $('.phlow-reg-facebook', widget),
            btnGoogle = $('.phlow-reg-google', widget),
            boxErrors = $('.phlow-reg-errors', widget),
            loader = $('.phlow-reg-loader', widget),
            isBusy = false;

        // Submit button
        btnSubmit.on('click', function() {
            if (isBusy) {
                return;
            }

            isBusy = true;
            loader.show();
            boxButtons.hide();
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
                    showErrors(res.errors);
                    return;
                }
                showMessage();
            });

            req.fail(function(err) {
                console.error(err);
            });

            req.always(finishProcessing);
        });

        // Facebook button
        btnFacebook.on('click', function() {
            if (isBusy) {
                return;
            }

            FB.login(function(fbres) {
                if (!fbres.authResponse) {
                    return;
                }

                startProcessing();

                var req = $.ajax({
                    method: 'POST',
                    url: phlowAjax.url,
                    dataType: 'json',
                    data: {
                        action: 'phlow_user_social_create',
                        facebookToken: fbres.authResponse.accessToken
                    }
                });

                req.done(function(res) {
                    if (!res.success) {
                        showErrors(res.errors);
                        return;
                    }
                    showMessage();
                });

                req.fail(function(err) {
                    console.error(err);
                });

                req.always(finishProcessing);
            }, { scope: config.facebook.scope });
        });

        // Google button
        btnGoogle.on('click', function() {
            if (isBusy) {
                return;
            }

            var auth2 = window.gapi.auth2.getAuthInstance();

            var options = {
                response_type: 'permission',
                // redirect_uri: '',
                fetch_basic_profile: true,
                prompt: '',
                scope: 'profile email'
            };

            auth2.signIn(options).then(
                function(glres) {
                    var authResponse = glres.getAuthResponse();

                    startProcessing();

                    var req = $.ajax({
                        method: 'POST',
                        url: phlowAjax.url,
                        dataType: 'json',
                        data: {
                            action: 'phlow_user_social_create',
                            googleToken: authResponse.access_token
                        }
                    });

                    req.done(function(res) {
                        if (!res.success) {
                            showErrors(res.errors);
                            return;
                        }
                        showMessage();
                    });

                    req.fail(function(err) {
                        console.error(err);
                    });

                    req.always(finishProcessing);
                },
                function(err) {
                    console.error(err);
                }
            );
        });

        function showErrors(errors) {
            errors.forEach(function(value) {
                var item = $('<li />').text(value);
                boxErrors.append(item);
            });

            boxErrors.show();
        }

        function showMessage() {
            var boxSuccess = $('<div />')
                .addClass('phlow-reg-success')
                .text('Thanks for registering to phlow. Please check your email for the next step');

            widget.html(boxSuccess);
        }

        function startProcessing() {
            isBusy = true;
            loader.show();
            boxButtons.hide();
            boxErrors.empty().hide();
        }

        function finishProcessing() {
            isBusy = false;
            loader.hide();
            boxButtons.show();
        }
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

    // Facebook
    window.fbAsyncInit = function() {
        FB.init({
            appId: config.facebook.appId,
            autoLogAppEvents: true,
            xfbml: true,
            version: 'v2.10'
        });
        FB.AppEvents.logPageView();
    };

    // Google
    window.gapiAsyncInit = function() {
        window.gapi.load('auth2', function() {
            if (window.gapi.auth2.getAuthInstance()) {
                return;
            }

            var params = {
                client_id: config.google.clientId,
                cookiepolicy: config.google.cookiePolicy
            };

            window.gapi.auth2.init(params).then(
                function() {},
                function(err) {}
            );
        });
    };
});

// Load Facebook API
(function(d, s, id){
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) {return;}
    js = d.createElement(s); js.id = id;
    js.src = "//connect.facebook.net/en_US/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));

// Load Google API
(function(d, s, id, cb) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) {return;}
    js = d.createElement(s); js.id = id;
    js.src = '//apis.google.com/js/client:platform.js';
    fjs.parentNode.insertBefore(js, fjs);
    js.onload = cb;
})(document, 'script', 'google-login', function() {
    window.gapiAsyncInit();
});
