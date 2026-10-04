# WP YouTube

A small WordPress plugin for YouTube playlists and galleries. It renders video posters in the initial HTML and serves thumbnails through the site's own domain. Visitor browsers do not contact YouTube until a visitor clicks a poster. Clicking loads the video from `youtube-nocookie.com`, which is outside this plugin's control and may use browser storage.

## Install and migrate

Install a release ZIP in WordPress. Set the YouTube Data API key under **Settings → WP YouTube** for galleries, or define `WPY_YOUTUBE_API_KEY` in `wp-config.php` (the constant takes precedence). Restrict the key to the YouTube Data API and the site's server IP. The key is used only in server requests.

Existing core YouTube embed blocks whose URL has a `list=` playlist ID render as a fast playlist poster when Embed Plus is deactivated. The legacy `[embedyt]...playlist URL...[/embedyt]` shortcode also works; URLs with `layout=gallery` keep gallery mode. For a gallery use:

```
[wp_youtube playlist="PLjcvwNsWxJC5sVZoFeEcJHNXuCeev9GvK" mode="gallery" limit="12"]
```

For a player near the top of the page use `mode="playlist" priority="high"`. This gives its first poster eager loading and high fetch priority. Gallery mode shows up to 50 current playlist items; it needs an API key. Without a key the plugin can show the playlist's first video through YouTube oEmbed, but cannot enumerate gallery items.

Do not run this alongside Embed Plus on production. On staging, record the old page output, disable Embed Plus, activate WP YouTube, and inspect all affected pages before removing the old plugin. Other Embed Plus features are not emulated. The site's separately coded YouTube list also requires separate migration.

Thumbnail requests go to `/?wpy_thumb=VIDEO_ID`. The plugin fetches a bounded JPEG from the fixed `i.ytimg.com` host, stores it under WordPress uploads, then serves it with a one-day browser cache. The first request to a thumbnail may be slower until it is cached; later renders use the static upload URL directly. Remove `wp-youtube/` under uploads to clear thumbnail files. Playlist metadata is cached for six hours, with a last-good fallback.

## Releases and updates

Only a published GitHub release with a `wp-youtube-X.Y.Z.zip` asset and a GitHub SHA-256 asset digest is offered to WordPress. The updater verifies the downloaded ZIP against that digest. The ZIP must contain a top-level `wp-youtube/` directory. Push a `vX.Y.Z` tag matching the plugin header to trigger the release workflow. The public GitHub repository needs no token on the site.

## Development

Run `composer install --no-plugins --no-scripts`, `vendor/bin/phpcs --standard=phpcs.xml.dist`, `php tests/run.php`, PHP lint, and `node --check assets/player.js`. GitHub Actions also runs lint, tests, CodeQL, and a Trivy filesystem scan. No PHP or JavaScript runtime dependencies are bundled.
