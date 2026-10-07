/* "YouTube playlist" block editor. No build step: plain wp.* globals. */
(function (wp) {
  'use strict';

  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var __ = wp.i18n.__;
  var be = wp.blockEditor;
  var c = wp.components;
  var ServerSideRender = wp.serverSideRender;
  var settings = window.wpyBlock || {hasKey: false, maxItems: 50};

  var HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];
  var ID = /^[A-Za-z0-9_-]{12,64}$/;

  /** Playlist ID from an ID or a YouTube URL with list=, else ''. Mirrors WP_YouTube::playlist_id(). */
  function playlistId(value) {
    var text = String(value || '').replace(/&amp;/gi, '&').replace(/^[\s"'“”„″«»]+|[\s"'“”„″«»]+$/g, '');
    if (ID.test(text)) return text;
    try {
      var url = new URL(text);
      if (HOSTS.indexOf(url.hostname.toLowerCase()) === -1) return '';
      var list = url.searchParams.get('list') || '';
      return ID.test(list) ? list : '';
    } catch (e) {
      return '';
    }
  }

  function isGalleryUrl(value) {
    try {
      return (new URL(String(value || '').replace(/&amp;/gi, '&')).searchParams.get('layout') || '').toLowerCase() === 'gallery';
    } catch (e) {
      return false;
    }
  }

  var icon = el('svg', {viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true},
    el('path', {fill: '#c4302b', d: 'M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z'}),
    el('path', {fill: '#fff', d: 'm10 15 5.2-3L10 9v6Z'}));

  /** URL form, shown until the block has a valid playlist (and when replacing it). */
  function UrlForm(props) {
    var draftState = useState(props.url || '');
    var draft = draftState[0];
    var setDraft = draftState[1];
    var touchedState = useState(false);
    var touched = touchedState[0];
    var setTouched = touchedState[1];
    var valid = playlistId(draft) !== '';

    function submit(event) {
      event.preventDefault();
      setTouched(true);
      if (valid) props.onSubmit(draft.trim());
    }

    return el(c.Placeholder, {
      icon: icon,
      label: __('YouTube playlist', 'wp-youtube'),
      instructions: __('Paste a YouTube playlist link (it contains list=) or a playlist ID.', 'wp-youtube'),
      className: 'wpy-placeholder',
    },
      el('form', {onSubmit: submit, className: 'wpy-placeholder__form'},
        el('input', {
          type: 'url',
          className: 'components-placeholder__input',
          'aria-label': __('Playlist link or ID', 'wp-youtube'),
          placeholder: 'https://www.youtube.com/playlist?list=…',
          value: draft,
          onChange: function (event) { setDraft(event.target.value); },
          onBlur: function () { setTouched(draft !== ''); },
        }),
        el(c.Button, {variant: 'primary', type: 'submit', disabled: draft === ''}, __('Embed', 'wp-youtube')),
        props.onCancel ? el(c.Button, {variant: 'tertiary', onClick: props.onCancel}, __('Cancel', 'wp-youtube')) : null
      ),
      touched && !valid ? el('p', {className: 'wpy-placeholder__error', role: 'alert'},
        __('This is not a playlist link. Open the playlist on YouTube and copy the address that contains "list=".', 'wp-youtube')) : null
    );
  }

  function Edit(props) {
    var attributes = props.attributes;
    var setAttributes = props.setAttributes;
    var editingState = useState(false);
    var editing = editingState[0];
    var setEditing = editingState[1];
    var blockProps = be.useBlockProps({className: 'wpy-block'});
    var id = playlistId(attributes.url);
    var gallery = attributes.mode === 'gallery';

    if (!id || editing) {
      return el('div', blockProps, el(UrlForm, {
        url: attributes.url,
        onSubmit: function (url) {
          setAttributes(isGalleryUrl(url) ? {url: url, mode: 'gallery'} : {url: url});
          setEditing(false);
        },
        onCancel: id ? function () { setEditing(false); } : null,
      }));
    }

    return el('div', blockProps,
      el(be.BlockControls, null,
        el(c.ToolbarGroup, null,
          el(c.ToolbarButton, {icon: 'edit', label: __('Change playlist', 'wp-youtube'), onClick: function () { setEditing(true); }}))),
      el(be.InspectorControls, null,
        el(c.PanelBody, {title: __('Playlist settings', 'wp-youtube')},
          el(c.TextControl, {
            __nextHasNoMarginBottom: true,
            label: __('Playlist link or ID', 'wp-youtube'),
            value: attributes.url,
            help: playlistId(attributes.url) ? __('Playlist ID: ', 'wp-youtube') + id : __('Not a playlist link.', 'wp-youtube'),
            onChange: function (url) { setAttributes({url: url}); },
          }),
          el(c.SelectControl, {
            __nextHasNoMarginBottom: true,
            label: __('Show', 'wp-youtube'),
            value: gallery ? 'gallery' : 'playlist',
            options: [
              {label: __('Player (one video, plays the playlist)', 'wp-youtube'), value: 'playlist'},
              {label: __('Gallery (a grid of the playlist\'s videos)', 'wp-youtube'), value: 'gallery'},
            ],
            onChange: function (mode) { setAttributes({mode: mode}); },
          }),
          gallery ? el(c.RangeControl, {
            __nextHasNoMarginBottom: true,
            label: __('Number of videos', 'wp-youtube'),
            min: 1,
            max: settings.maxItems || 50,
            value: attributes.limit || 12,
            onChange: function (limit) { setAttributes({limit: limit || 12}); },
          }) : null,
          gallery && !settings.hasKey ? el(c.Notice, {status: 'warning', isDismissible: false},
            __('A gallery needs a YouTube Data API key (Settings → WP YouTube). Without one it shows only the first video.', 'wp-youtube')) : null,
          el(c.TextControl, {
            __nextHasNoMarginBottom: true,
            label: __('List title', 'wp-youtube'),
            value: attributes.listTitle || '',
            onChange: function (listTitle) { setAttributes({listTitle: listTitle}); },
          }),
          el(c.TextControl, {
            __nextHasNoMarginBottom: true,
            label: __('Title link (optional)', 'wp-youtube'),
            type: 'url',
            value: attributes.listTitleUrl || '',
            onChange: function (listTitleUrl) { setAttributes({listTitleUrl: listTitleUrl}); },
          }),
          el(c.SelectControl, {
            __nextHasNoMarginBottom: true,
            label: __('Title position', 'wp-youtube'),
            value: attributes.titlePosition || 'below',
            options: [
              {label: __('Below videos', 'wp-youtube'), value: 'below'},
              {label: __('Above videos', 'wp-youtube'), value: 'above'},
            ],
            onChange: function (titlePosition) { setAttributes({titlePosition: titlePosition}); },
          }),
          el(c.ToggleControl, {
            __nextHasNoMarginBottom: true,
            label: __('Near the top of the page', 'wp-youtube'),
            help: __('Loads the first poster straight away with high priority. Use it for one player above the fold.', 'wp-youtube'),
            checked: !!attributes.priority,
            onChange: function (priority) { setAttributes({priority: priority}); },
          }))),
      // The preview is the real server output; clicks are disabled so the editor never loads YouTube.
      el('div', {className: 'wpy-editor-preview', inert: ''},
        el(ServerSideRender, {block: 'wp-youtube/playlist', attributes: {url: attributes.url, mode: attributes.mode, limit: attributes.limit, listTitle: attributes.listTitle, listTitleUrl: attributes.listTitleUrl, titlePosition: attributes.titlePosition}}))
    );
  }

  /** URL of a shortcode: url= or playlist=, else its content (pasting may have turned the URL into a link). */
  function fromShortcode(named, data) {
    var content = data && data.shortcode && typeof data.shortcode.content === 'string' ? data.shortcode.content : '';
    content = content.replace(/&amp;/gi, '&').trim();
    // A pasted link can arrive inside an HTML anchor. Extract its URL without parsing markup.
    var link = content.match(/https?:\/\/[^\s"'<>]+/i);
    var value = link ? link[0] : content;
    return named.url || named.playlist || (playlistId(value) ? value : '');
  }

  wp.blocks.registerBlockType('wp-youtube/playlist', {
    apiVersion: 3,
    title: __('YouTube playlist', 'wp-youtube'),
    description: __('A fast YouTube playlist player or video gallery. Nothing loads from YouTube until a visitor clicks play.', 'wp-youtube'),
    category: 'embed',
    icon: icon,
    keywords: ['youtube', 'video', 'playlist', 'gallery', 'grojaraštis', 'vaizdo'],
    attributes: {
      url: {type: 'string', default: ''},
      mode: {type: 'string', default: 'playlist'},
      limit: {type: 'number', default: 12},
      priority: {type: 'boolean', default: false},
      listTitle: {type: 'string', default: ''},
      listTitleUrl: {type: 'string', default: ''},
      titlePosition: {type: 'string', default: 'below'},
    },
    supports: {align: ['wide', 'full'], anchor: true, html: false},
    example: {attributes: {url: 'https://www.youtube.com/playlist?list=PLjcvwNsWxJC5sVZoFeEcJHNXuCeev9GvK'}},
    transforms: {
      from: [
        {
          type: 'block',
          blocks: ['core/embed'],
          isMatch: function (attributes) { return playlistId(attributes.url) !== ''; },
          transform: function (attributes) {
            return wp.blocks.createBlock('wp-youtube/playlist', {url: attributes.url, mode: isGalleryUrl(attributes.url) ? 'gallery' : 'playlist'});
          },
        },
        {
          type: 'shortcode',
          tag: 'wp_youtube',
          attributes: {
            url: {type: 'string', shortcode: function (attrs) { return fromShortcode(attrs.named, null); }},
            mode: {type: 'string', shortcode: function (attrs) { return attrs.named.mode === 'playlist' ? 'playlist' : 'gallery'; }},
            limit: {type: 'number', shortcode: function (attrs) { return parseInt(attrs.named.limit, 10) || 12; }},
            priority: {type: 'boolean', shortcode: function (attrs) { return attrs.named.priority === 'high'; }},
          },
        },
        {
          type: 'shortcode',
          tag: 'embedyt',
          attributes: {
            url: {type: 'string', shortcode: function (attrs, data) { return fromShortcode(attrs.named, data); }},
            mode: {type: 'string', shortcode: function (attrs, data) {
              var url = fromShortcode(attrs.named, data);
              return isGalleryUrl(url) || (attrs.named.layout || '').toLowerCase() === 'gallery' ? 'gallery' : 'playlist';
            }},
          },
        },
        {
          // Pasting a playlist link on its own line creates the block.
          type: 'raw',
          isMatch: function (node) {
            return node.nodeName === 'P' && playlistId(node.textContent.trim()) !== '' && /^https?:\/\//.test(node.textContent.trim());
          },
          transform: function (node) {
            var url = node.textContent.trim();
            return wp.blocks.createBlock('wp-youtube/playlist', {url: url, mode: isGalleryUrl(url) ? 'gallery' : 'playlist'});
          },
        },
      ],
    },
    edit: Edit,
    save: function () { return null; },
  });
})(window.wp);
