/* The only visitor-side script. No requests or storage access until a click. */
document.addEventListener('click', function (event) {
  const button = event.target instanceof Element ? event.target.closest('.wpy-play') : null;
  if (!button) return;
  const video = button.dataset.video || '';
  const playlist = button.dataset.playlist || '';
  if (!/^[A-Za-z0-9_-]{11}$/.test(video) || !/^[A-Za-z0-9_-]{12,64}$/.test(playlist)) return;
  const iframe = document.createElement('iframe');
  const url = new URL('https://www.youtube-nocookie.com/embed/' + video);
  url.searchParams.set('autoplay', '1');
  url.searchParams.set('playsinline', '1');
  url.searchParams.set('list', playlist);
  iframe.src = url.toString();
  iframe.title = button.getAttribute('aria-label') || 'YouTube video';
  iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share';
  iframe.allowFullscreen = true;
  // YouTube refuses embeds that send no Referer ("Error 153"). This policy sends
  // only the site's origin, never the page path.
  iframe.referrerPolicy = 'strict-origin-when-cross-origin';
  iframe.className = 'wpy-iframe';
  button.replaceWith(iframe);
  iframe.focus();
});
