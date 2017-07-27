(function() {
    tinymce.PluginManager.add('phlow_stream', function(editor, url) {
        var sh_tag = 'phlow_stream';

        //helper functions
        function getAttr(s, n) {
            n = new RegExp(n + '=\"([^\"]+)\"', 'g').exec(s);
            return n ?  window.decodeURIComponent(n[1]) : '';
        };

        function html(cls, data, con) {
            var placeholder = url + '/img/' + getAttr(data,'type') + '.jpg';
            data = window.encodeURIComponent( data );
            content = window.encodeURIComponent( con );

            return '<img src="' + placeholder + '" class="mceItem ' + cls + '" ' + 'data-sh-attr="' + data + '" data-sh-content="'+ con+'" data-mce-resize="false" data-mce-placeholder="1" />';
        }

        function replaceShortcodes(content) {
            return content.replace( /\[phlow_stream([^\]]*)\]([^\]]*)\[\/phlow_stream\]/g, function( all,attr,con) {
                return html( 'wp-phlow_stream', attr , con);
            });
        }

        function restoreShortcodes( content ) {
            return content.replace( /(?:<p(?: [^>]+)?>)*(<img [^>]+>)(?:<\/p>)*/g, function( match, image ) {
                var data = getAttr( image, 'data-sh-attr' );
                var con = getAttr( image, 'data-sh-content' );

                if ( data ) {
                    return '<p>[' + sh_tag + data + ']' + con + '[/'+sh_tag+']</p>';
                }
                return match;
            });
        }

        //add popup
        editor.addCommand('phlow_stream_popup', function(ui, v) {
            editor.windowManager.open({
                title: 'Generate widget shortcode',
                width: 400,
                height: 500,
                url: phlow_plugin.url + 'mce_plugin/dialog.html'
            }, phlow_plugin);
        });

        //add button
        editor.addButton('phlow_stream', {
            image : phlow_plugin.url + 'js/phlow-logo.jpg',
            tooltip: 'phlow widget generator',
            onclick: function() {
                editor.execCommand('phlow_stream_popup','',{
                    header : '',
                    footer : '',
                    type   : 'default',
                    content: ''
                });
            }
        });

        //replace from shortcode to an image placeholder
        editor.on('BeforeSetcontent', function(event){
            event.content = replaceShortcodes( event.content );
        });

        //replace from image placeholder to shortcode
        editor.on('GetContent', function(event){
            event.content = restoreShortcodes(event.content);
        });

        //open popup on placeholder double click
        editor.on('DblClick',function(e) {
            var cls  = e.target.className.indexOf('wp-phlow_stream');
            if ( e.target.nodeName == 'IMG' && e.target.className.indexOf('wp-phlow_stream') > -1 ) {
                var title = e.target.attributes['data-sh-attr'].value;
                title = window.decodeURIComponent(title);
                console.log(title);
                var content = e.target.attributes['data-sh-content'].value;
                editor.execCommand('phlow_stream_popup','',{
                    header : getAttr(title,'header'),
                    footer : getAttr(title,'footer'),
                    type   : getAttr(title,'type'),
                    content: content
                });
            }
        });
    });
})();