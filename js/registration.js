jQuery(function($) {

    function widgetInit() {
        var widget = this;

        var tabs = $('.phlow-reg-tabs li', widget),
            isLoginTab = false,
            inputEmail = $('.phlow-reg-email', widget),
            inputPasswd = $('.phlow-reg-passwd', widget),
            btnSubmit = $('.phlow-reg-submit', widget),
            btnFacebook = $('.phlow-reg-facebook', widget),
            btnTwitter = $('.phlow-reg-twitter', widget),
            btnGoogle = $('.phlow-reg-google', widget),
            boxErrors = $('.phlow-reg-errors', widget),
            loader = $('.phlow-reg-loader', widget),
            tags = widget.data('tags'),
            list = widget.data('list'),
            group = widget.data('group'),
            isBusy = false;

        var buttonsText = {
            register: {
                default: 'Register',
                facebook: 'Sign up with Facebook',
                google: 'Sign up with Google',
                twitter: 'Sign up with Twitter'
            },
            login: {
                default: 'Login',
                facebook: 'Sign in with Facebook',
                google: 'Sign in with Google',
                twitter: 'Sign in with Twitter'
            }
        };

        // Tabs init
        tabs.on('click', function() {
            var tab = $(this),
                currentTab = tab.data('tab'),
                activeClass = 'active';

            isLoginTab = (currentTab === 'login');

            // Hide errors
            boxErrors.empty().hide();

            // Set active tab
            tabs.removeClass(activeClass);
            tab.addClass(activeClass);

            // Set buttons text
            var texts = buttonsText[currentTab];
            btnSubmit.text(texts.default);
            btnFacebook.text(texts.facebook);
            btnGoogle.text(texts.google);
            btnTwitter.text(texts.twitter);
        });

        // Submit button
        btnSubmit.on('click', function() {
            if (isBusy) {
                return;
            }

            startProcessing();

            var data = {
                action: isLoginTab ? 'phlow_user_login' : 'phlow_user_create',
                email: inputEmail.val(),
                password: inputPasswd.val()
            };

            if (tags) data.tags = tags;
            if (list) data.list = list;
            if (group) data.group = group;

            // Get referral code
            var queryString = parseQueryString(location.search);
            if (queryString.referralCode) {
                data.referralcode = queryString.referralCode;
            }

            var req = $.ajax({
                method: 'POST',
                url: phlowAjax.url,
                dataType: 'json',
                data: data
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

                var data = {
                    action: 'phlow_user_social_create',
                    facebookToken: fbres.authResponse.accessToken
                };

                if (tags) data.tags = tags;
                if (list) data.list = list;
                if (group) data.group = group;

                // Get referral code
                var queryString = parseQueryString(location.search);
                if (queryString.referralCode) {
                    data.referralcode = queryString.referralCode;
                }

                var req = $.ajax({
                    method: 'POST',
                    url: phlowAjax.url,
                    dataType: 'json',
                    data: data
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
            }, { scope: 'email, user_about_me, user_birthday, user_website, public_profile' });
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

                    var data = {
                        action: 'phlow_user_social_create',
                        googleToken: authResponse.access_token
                    };

                    if (tags) data.tags = tags;
                    if (list) data.list = list;
                    if (group) data.group = group;

                    // Get referral code
                    var queryString = parseQueryString(location.search);
                    if (queryString.referralCode) {
                        data.referralcode = queryString.referralCode;
                    }

                    var req = $.ajax({
                        method: 'POST',
                        url: phlowAjax.url,
                        dataType: 'json',
                        data: data
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

        // Twitter button
        btnTwitter.on('click', function() {
            if (isBusy) {
                return;
            }

            var width = 600,
                height = 400,
                left = (screen.width / 2) - (width / 2),
                top = 100;

            var popupUrl = '',
                popupName = 'twitterLoginWindow',
                popupOptions = 'width=' + width + ', height=' + height + ', left=' + left + ', top=' + top;

            var popup = window.open(popupUrl, popupName, popupOptions);

            twitterRequestToken()
                .done(function(res) {
                    var url = 'https://api.twitter.com/oauth/authenticate?oauth_token=' + res.token;
                    popup.location.href = url;
                    twitterPopupPolling(popup);
                })
                .fail(console.error);
        });

        // Get Twitter request token
        function twitterRequestToken() {
            var d = $.Deferred();

            var req = $.ajax({
                method: 'GET',
                url: phlowAjax.url,
                dataType: 'json',
                data: {
                    action: 'phlow_twitter_request_token'
                }
            });

            req.done(d.resolve);
            req.fail(d.reject);

            return d.promise();
        }

        // Get Twitter access token
        function twitterAccessToken(verifier, token) {
            startProcessing();

            var req = $.ajax({
                method: 'GET',
                url: phlowAjax.url,
                dataType: 'json',
                data: {
                    action: 'phlow_twitter_access_token',
                    verifier: verifier,
                    token: token
                }
            });

            req.done(function(res) {
                if (res.success) {
                    var secret = res.data.token_secret,
                        token = res.data.token;

                    twitterRegistration(token, secret);
                }
                else {
                    console.error(res.errors);
                }
            });

            req.fail(function(err) {
                finishProcessing();
                console.error(err);
            });
        }

        // Twitter registration
        function twitterRegistration(token, secret) {
            var data = {
                action: 'phlow_user_social_create',
                twitterTokenSecret: secret,
                twitterToken: token
            };

            if (tags) data.tags = tags;
            if (list) data.list = list;
            if (group) data.group = group;

            // Get referral code
            var queryString = parseQueryString(location.search);
            if (queryString.referralCode) {
                data.referralcode = queryString.referralCode;
            }

            var req = $.ajax({
                method: 'POST',
                url: phlowAjax.url,
                dataType: 'json',
                data: data
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
        }

        // Twitter popup polling
        function twitterPopupPolling(popup) {
            var polling = setInterval(function() {
                if (!popup || popup.closed || popup.closed === undefined) {
                    clearInterval(polling);
                    console.log('Popup has been closed by user');
                }

                var closeDialog = function() {
                    clearInterval(polling);
                    popup.close();
                }

                try {
                    var host = popup.location.hostname;

                    if (host.indexOf('api.twitter.com') === -1 && host != '') {
                        var search = popup.location.search;

                        if (search) {
                            var queryString = search.substring(1),
                                vars = queryString.split('&'),
                                query = {};

                            vars.forEach(function(item) {
                                var pair = item.split('=');
                                query[pair[0]] = pair[1];
                            });

                            var verifier = query.oauth_verifier,
                                token = query.oauth_token;

                            closeDialog();
                            twitterAccessToken(verifier, token);
                        }
                        else {
                            closeDialog();
                            console.error(
                                'OAuth redirect has occurred but no query or hash parameters were found. ' +
                                'They were either not set during the redirect, or were removed—typically by a ' +
                                'routing library—before Twitter react component could read it.'
                            );
                        }
                    }
                } catch(err) {
                    // Ignore DOMException: Blocked a frame with origin from accessing a cross-origin frame
                }
            }, 500);
        }

        function showErrors(errors) {
            errors.forEach(function(value) {
                var item = $('<li />').text(value);
                boxErrors.append(item);
            });

            boxErrors.show();
        }

        function showMessage() {
            var message = isLoginTab
                ? 'You have been successfully logged in phlow'
                : 'Thanks for registering to phlow. Please check your email for the next step';

            var boxSuccess = $('<div />')
                .addClass('phlow-reg-success')
                .text(message);

            widget.html(boxSuccess);
        }

        function startProcessing() {
            isBusy = true;
            loader.show();
            boxErrors.empty().hide();
        }

        function finishProcessing() {
            isBusy = false;
            loader.hide();
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

    // Parsing query string
    function parseQueryString(url) {
        var urlParams = {};

        url.replace(new RegExp("([^?=&]+)(=([^&]*))?", "g"), function($0, $1, $2, $3) {
            urlParams[$1] = $3;
        });

        return urlParams;
    }

    // Facebook
    window.fbAsyncInit = function() {
        FB.init({
            appId: phlowAjax.facebook_app_id,
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
                client_id: phlowAjax.google_client_id,
                cookiepolicy: 'single_host_origin'
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
