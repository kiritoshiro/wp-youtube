/* The only visitor-side script. No requests or storage access until a click. */
(function () {
  const VIDEO = /^[A-Za-z0-9_-]{11}$/;
  const LIST = /^[A-Za-z0-9_-]{12,64}$/;

  function player(video, playlist, title) {
    const iframe = document.createElement('iframe');
    const url = new URL('https://www.youtube-nocookie.com/embed/' + video);
    url.searchParams.set('autoplay', '1');
    url.searchParams.set('playsinline', '1');
    url.searchParams.set('list', playlist);
    iframe.src = url.toString();
    iframe.title = title || 'YouTube video';
    iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen';
    // YouTube refuses embeds that send no Referer ("Error 153"). This policy sends
    // only the site's origin, never the page path.
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.className = 'wpy-iframe';
    return iframe;
  }

  /** A gallery thumbnail plays its video in the gallery's large player. */
  function playInGallery(thumb) {
    const gallery = thumb.closest('.wpy-gallery');
    const feature = gallery && gallery.querySelector('.wpy-feature');
    const first = gallery && gallery.querySelector('.wpy-feature .wpy-play, .wpy-feature .wpy-iframe');
    const playlist = first && first.dataset.playlist;
    const video = thumb.dataset.video || '';
    if (!feature || !VIDEO.test(video) || !LIST.test(playlist || '')) return;
    const iframe = player(video, playlist, thumb.getAttribute('aria-label'));
    iframe.dataset.playlist = playlist;
    feature.replaceChildren(iframe);

    gallery.querySelectorAll('.wpy-item').forEach(function (item) {
      const current = item.querySelector('.wpy-thumb') === thumb;
      item.classList.toggle('is-active', current);
      const button = item.querySelector('.wpy-thumb');
      if (current) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current');
    });
    const link = thumb.parentElement.querySelector('.wpy-title');
    const title = gallery.querySelector('.wpy-feature-title a');
    if (link && title) {
      title.href = link.href;
      title.textContent = link.textContent;
    }
    const rect = feature.getBoundingClientRect();
    if (rect.top < 0 || rect.bottom > window.innerHeight) {
      feature.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  }

  /** "Show more videos" reveals the next batch; hidden thumbnails load only now. */
  function showMore(button) {
    const gallery = button.closest('.wpy-gallery');
    if (!gallery) return;
    const step = Math.max(1, parseInt(gallery.dataset.step, 10) || 12);
    const hidden = gallery.querySelectorAll('.wpy-item[hidden]');
    for (let i = 0; i < hidden.length && i < step; i++) hidden[i].hidden = false;
    if (hidden.length <= step) {
      button.remove();
    }
    const next = hidden[0] && hidden[0].querySelector('.wpy-thumb');
    if (next) next.focus({preventScroll: true});
  }

  document.addEventListener('click', function (event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    const thumb = target.closest('.wpy-thumb');
    if (thumb) {
      playInGallery(thumb);
      return;
    }
    const more = target.closest('.wpy-more');
    if (more) {
      showMore(more);
      return;
    }
    const button = target.closest('.wpy-play');
    if (!button) return;
    const video = button.dataset.video || '';
    const playlist = button.dataset.playlist || '';
    if (!VIDEO.test(video) || !LIST.test(playlist)) return;
    const iframe = player(video, playlist, button.getAttribute('aria-label'));
    iframe.dataset.playlist = playlist;
    button.replaceWith(iframe);
    iframe.focus();
  });
})();
