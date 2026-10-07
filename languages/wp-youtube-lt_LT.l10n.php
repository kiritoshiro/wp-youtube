<?php
// Lithuanian strings for the server-rendered parts of the plugin, in the PHP
// translation format WordPress 6.5+ loads directly (no .mo file needed).
// tests/run.php checks that every PHP string is listed.
defined( 'ABSPATH' ) || exit;

return array(
	'domain'       => 'wp-youtube',
	'language'     => 'lt_LT',
	'plural-forms' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2);',
	'messages'     => array(
		'A gallery needs a YouTube Data API key (Settings → WP YouTube, or the ALPS key under Settings → Media). Without one it shows only the first video.' => 'Galerijai reikia YouTube Data API rakto (Nustatymai → WP YouTube arba ALPS raktas skiltyje Nustatymai → Medija). Be jo rodomas tik pirmas vaizdo įrašas.',
		'All videos on YouTube'                                               => 'Visi vaizdo įrašai „YouTube“',
		'In use: key …%1$s from %2$s.'                                        => 'Naudojamas raktas …%1$s, nustatytas: %2$s.',
		'No key is set.'                                                      => 'Raktas nenustatytas.',
		'Play %s on YouTube'                                                  => 'Paleisti: %s',
		'Play %s'                                                             => 'Paleisti: %s',
		'Settings → Media (ALPS Gutenberg Blocks)'                            => 'Nustatymai → Medija (ALPS Gutenberg Blocks)',
		'Settings → WP YouTube'                                               => 'Nustatymai → WP YouTube',
		'Show more videos'                                                    => 'Rodyti daugiau vaizdo įrašų',
		'The playlist could not be read from YouTube (%s), so only the first video is shown.' => 'Nepavyko gauti grojaraščio iš YouTube (%s), todėl rodomas tik pirmas vaizdo įrašas.',
		'Videos are temporarily unavailable.'                                 => 'Vaizdo įrašai laikinai nepasiekiami.',
		'YouTube playlist'                                                    => 'YouTube grojaraštis',
		'YouTube playlist: add a playlist link (with list=) or a playlist ID.' => 'YouTube grojaraštis: įrašykite grojaraščio nuorodą (su list=) arba grojaraščio ID.',
		'YouTube: [embedyt] needs a playlist link (with list=).'              => 'YouTube: [embedyt] reikia grojaraščio nuorodos (su list=).',
		'YouTube: [wp_youtube] needs playlist="…" with a playlist link or ID.' => 'YouTube: [wp_youtube] reikia playlist="…" su grojaraščio nuoroda arba ID.',
	),
);
