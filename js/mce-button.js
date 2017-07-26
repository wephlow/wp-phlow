console.log(phlow_plugin.src);

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
            //setup defaults
            var tag = '';
            if (v.tag) {
                tag = v.tag;
            }

            var wm = editor.windowManager.open({
                title: 'generate shortcode',
                body: [
                    // {
                    //     type: 'listbox',
                    //     name: 'source',
                    //     label: 'Source',
                    //     values: phlow_plugin.src.options.map(function(value, key) {
                    //         return { text: value, value: key }
                    //     }),
                    //     value: phlow_plugin.src.selected,
                    //     tooltip: 'Leave blank for none',
                    //     onselect: function(e) {
                    //         var tags = wm.find('#tags')[0];
                    //         console.log(tags);
                    //         tags.visible( Boolean(this.value() == 0) );
                    //     }
                    // },
                    {
                        type: 'textbox',
                        name: 'tags',
                        label: 'Tags',
                        value: tag,
                        tooltip: 'Leave blank for none',
                        hidden: Boolean( phlow_plugin.src.selected != 0 )
                    },
                    // {
                    //     type: 'listbox',
                    //     name: 'type',
                    //     label: 'Type of widget',
                    //     values: phlow_plugin.type.options.map(function(value, key) {
                    //         return { text: value, value: key }
                    //     }),
                    //     value: phlow_plugin.type.selected,
                    //     tooltip: 'Leave blank for none'
                    // },
                    {
                        type: 'checkbox',
                        name: 'footer',
                        disabled: true,
                        label: 'require clean streams',
                    },
                    {
                        type: 'checkbox',
                        name: 'nudity',
                        checked: phlow_plugin.nudity,
                        label: 'allow images containing nudity',
                    },
                    {
                        type: 'checkbox',
                        name: 'violence',
                        checked: phlow_plugin.violence,
                        label: 'allow violent images',
                    }
                ],
                onsubmit: function( e ) {
                    var shortcode_str = '[' + sh_tag ;
                    //check for header
                    if (typeof e.data.tags != 'undefined' && e.data.tags.length)
                        shortcode_str += ' tags="' + e.data.tags + '"';

                    if (e.data.footer === "1"){
                        shortcode_str += ' clean="1"';
                    }else{
                        shortcode_str += ' clean="0"';
                    }
                    if (e.data.nudity == true){
                        shortcode_str += ' nudity="1"';
                    }else{
                        shortcode_str += ' nudity="0"';
                    }

                    if (e.data.violence == true){
                        shortcode_str += ' violence="1"';
                    }else{
                        shortcode_str += ' violence="0"';
                    }

                    //add panel content
                    shortcode_str += '][/' + sh_tag + ']';
                    //insert shortcode to tinymce
                    editor.insertContent( shortcode_str);
                }
            });
        });

        //add button
        editor.addButton('phlow_stream', {
            image : phlow_plugin.url + 'js/phlow-logo.jpg',
            tooltip: 'phlow stream generator',
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