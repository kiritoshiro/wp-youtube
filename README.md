# WP YouTube

A small WordPress plugin for YouTube playlists and galleries. It renders video posters in the initial HTML and serves thumbnails through the site's own domain. Visitor browsers do not contact YouTube until a visitor clicks a poster. Clicking loads the video from `youtube-nocookie.com`, which is outside this plugin's control and may use browser storage. Video frames have square corners. The block can show an optional, linked list title above or below the videos; no text appears under a player unless its title is set.

## Install and migrate

Install a release ZIP in WordPress. Set the YouTube Data API key under **Settings → WP YouTube** for galleries, or define `WPY_YOUTUBE_API_KEY` in `wp-config.php` (the constant takes precedence). Restrict the key to the YouTube Data API and the site's server IP. The key is used only in server requests.

## The YouTube playlist block

In the editor, add the **YouTube playlist** block (Embeds category) and paste a playlist link (it contains `list=`) or a playlist ID. The block checks the link as you type. In its settings:

- **Show:** a player (one poster that plays the playlist) or a gallery (needs the API key below): the first video as a large player, then a grid of the playlist's videos with their titles. A thumbnail plays its video in the large player; a title opens the video on YouTube. Below the grid, "Show more videos" and a link to the whole playlist on YouTube.
- **Videos shown at first:** for galleries, 1–100. "Show more videos" reveals the rest in steps of this size; hidden thumbnails are not downloaded until shown.
- **List title / Title link / Title position:** optionally show your own linked title above or below the videos (below by default).
- **Near the top of the page:** loads the first poster straight away with high priority; use it for one player above the fold.

The preview in the editor is the real page output and is not clickable, so the editor never loads YouTube. Pasting a playlist link on its own line, or a `[wp_youtube]` or `[embedyt]` shortcode, creates the block; a YouTube embed block with a playlist link can be transformed into it.

A wrong link or shortcode never breaks the page: visitors see nothing, and people who can edit the page see a short note saying what is missing. A missing or extra closing tag (`[/embedyt]`, `[/wp_youtube]`) can no longer swallow the content after it.

## Shortcodes

Existing core YouTube embed blocks whose URL has a `list=` playlist ID render as a fast playlist poster when Embed Plus is deactivated. The legacy `[embedyt]...playlist URL...[/embedyt]` shortcode also works; URLs with `layout=gallery` keep gallery mode. For a gallery use:

```
[wp_youtube playlist="PLjcvwNsWxJC5sVZoFeEcJHNXuCeev9GvK" mode="gallery" limit="12"]
```

For a player near the top of the page use `mode="playlist" priority="high"`. This gives its first poster eager loading and high fetch priority. Gallery mode shows up to 100 current playlist items; it needs an API key. Without a key the plugin can show the playlist's first video through YouTube oEmbed, but cannot enumerate gallery items, and editors see a note saying so.

## API key

Settings → WP YouTube, or `define( 'WPY_YOUTUBE_API_KEY', '…' );`. If neither is set, the key of the ALPS Gutenberg Blocks plugin (Settings → Media, or `ALPS_YOUTUBE_API_KEY`) is used, so one key serves both YouTube blocks. The settings page shows which key is in use. Requests send the site's address as referrer, so a key restricted to this website works; a key restricted to the YouTube Data API v3 without a website restriction also works. Lists are cached per key for 30 minutes. After that visitors get the last list at once while WP-Cron fetches the new one in the background, so a newly added video appears within about half an hour (if WP-Cron does not run, the page fetches it once the refresh is 10 minutes late). Settings → WP YouTube → **Refresh playlists now** fetches every playlist the site has shown immediately. A new key is used at once, and a failed fetch is retried after 10 minutes while editors see the reason.

Do not run this alongside Embed Plus on production. On staging, record the old page output, disable Embed Plus, activate WP YouTube, and inspect all affected pages before removing the old plugin. Other Embed Plus features are not emulated. The site's separately coded YouTube list also requires separate migration.

Thumbnail requests go to `/?wpy_thumb=VIDEO_ID`. The plugin fetches a bounded JPEG from the fixed `i.ytimg.com` host, stores it under WordPress uploads, then serves it with a one-day browser cache. The first request to a thumbnail may be slower until it is cached; later renders use the static upload URL directly. Remove `wp-youtube/` under uploads to clear thumbnail files. Playlist metadata is cached for 30 minutes and refreshed in the background, with a last-good fallback.

## Releases and updates

Only a published GitHub release with a `wp-youtube-X.Y.Z.zip` asset and a GitHub SHA-256 asset digest is offered to WordPress. The updater verifies the downloaded ZIP against that digest. The ZIP must contain a top-level `wp-youtube/` directory. Push a `vX.Y.Z` tag matching the plugin header to trigger the release workflow. The public GitHub repository needs no token on the site. The latest release is cached for six hours; "Check again" on Dashboard → Updates skips the cache.

## Development

Run `composer install --no-plugins --no-scripts`, `vendor/bin/phpcs --standard=phpcs.xml.dist`, `php tests/run.php`, PHP lint, and `node --check assets/player.js`. GitHub Actions also runs lint, tests, CodeQL, and a Trivy filesystem scan. No PHP or JavaScript runtime dependencies are bundled.
