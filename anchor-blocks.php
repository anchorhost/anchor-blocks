<?php
/**
 * Plugin Name: Anchor Blocks
 * Description: Custom blocks for Anchor Hosting blog posts — Conversation, Timeline, Callout, Stats Dashboard, Bar Chart, Report Card, Indicators of Compromise, Vector Cards, Term List, Data Table, Canvas, and Audio.
 * Version: 1.9.0
 * Author: Austin Ginder
 * Author URI: https://anchor.host
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ANCHOR_BLOCKS_VERSION', '1.9.0' );
define( 'ANCHOR_BLOCKS_URL', plugin_dir_url( __FILE__ ) );
define( 'ANCHOR_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );

require_once ANCHOR_BLOCKS_DIR . 'app/Updater.php';
require_once ANCHOR_BLOCKS_DIR . 'app/MinnAdmin.php';
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ANCHOR_BLOCKS_DIR . 'app/Cli.php';
}
new AnchorBlocks\Updater();

add_action( 'init', function() {
	wp_register_script(
		'anchor-blocks-editor',
		ANCHOR_BLOCKS_URL . 'editor.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
		ANCHOR_BLOCKS_VERSION,
		true
	);

	// Front-end player for anchor/audio. Enqueued only on pages that render one.
	wp_register_script(
		'anchor-blocks-audio',
		ANCHOR_BLOCKS_URL . 'audio.js',
		[],
		ANCHOR_BLOCKS_VERSION,
		[ 'in_footer' => true, 'strategy' => 'defer' ]
	);

	wp_register_style(
		'anchor-blocks-style',
		ANCHOR_BLOCKS_URL . 'css/style.css',
		[],
		ANCHOR_BLOCKS_VERSION
	);

	wp_register_style(
		'anchor-blocks-editor-style',
		ANCHOR_BLOCKS_URL . 'css/editor.css',
		[ 'anchor-blocks-style' ],
		ANCHOR_BLOCKS_VERSION
	);

	$block_args = [
		'api_version'   => 3,
		'editor_script' => 'anchor-blocks-editor',
		'editor_style'  => 'anchor-blocks-editor-style',
		'style'         => 'anchor-blocks-style',
	];

	// The PHP registration is the ONLY registry server-side consumers see
	// (REST block-types, Minn Admin's auto-insert and inspector, any headless
	// tooling) — editor.js declarations are invisible to them. So titles,
	// parent gating and enum choice lists are declared here and must stay in
	// sync with editor.js; the SelectControl option lists there are the
	// ground truth for each enum.

	// Parent blocks — save handled in JS via InnerBlocks.Content
	register_block_type( 'anchor/conversation', array_merge( $block_args, [
		'title' => 'Conversation',
	] ) );
	register_block_type( 'anchor/timeline', array_merge( $block_args, [
		'title' => 'Timeline',
	] ) );

	// Parent blocks with server-rendered children — need render_callback
	register_block_type( 'anchor/stats-dashboard', array_merge( $block_args, [
		'title'           => 'Stats Dashboard',
		'render_callback' => 'anchor_blocks_render_stats_dashboard',
	] ) );

	register_block_type( 'anchor/bar-chart', array_merge( $block_args, [
		'title'           => 'Bar Chart',
		'render_callback' => 'anchor_blocks_render_bar_chart',
		'attributes'      => [
			'title' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	register_block_type( 'anchor/ioc-list', array_merge( $block_args, [
		'title'           => 'Indicators of Compromise',
		'render_callback' => 'anchor_blocks_render_ioc_list',
		'attributes'      => [
			'title' => [ 'type' => 'string', 'default' => 'Indicators of Compromise' ],
		],
	] ) );

	register_block_type( 'anchor/vector-cards', array_merge( $block_args, [
		'title'           => 'Vector Cards',
		'render_callback' => 'anchor_blocks_render_vector_cards',
		'attributes'      => [
			'title' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	register_block_type( 'anchor/term-list', array_merge( $block_args, [
		'title'           => 'Term List',
		'render_callback' => 'anchor_blocks_render_term_list',
		'attributes'      => [
			'title' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	// Child/leaf blocks — server-side rendered to avoid validation errors.
	// `parent` mirrors editor.js so children never offer as top-level inserts.
	register_block_type( 'anchor/conversation-message', array_merge( $block_args, [
		'title'           => 'Conversation Message',
		'parent'          => [ 'anchor/conversation' ],
		'render_callback' => 'anchor_blocks_render_conversation_message',
		'attributes'      => [
			'role'    => [ 'type' => 'string', 'default' => 'user', 'enum' => [ 'user', 'assistant' ] ],
			'label'   => [ 'type' => 'string', 'default' => 'Austin' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	register_block_type( 'anchor/timeline-item', array_merge( $block_args, [
		'title'           => 'Timeline Item',
		'parent'          => [ 'anchor/timeline' ],
		'render_callback' => 'anchor_blocks_render_timeline_item',
		'attributes'      => [
			'date'    => [ 'type' => 'string', 'default' => '' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
			'color'   => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'red', 'orange', 'green' ] ],
		],
	] ) );

	register_block_type( 'anchor/callout', array_merge( $block_args, [
		'title'           => 'Callout',
		'render_callback' => 'anchor_blocks_render_callout',
		'attributes'      => [
			'title'   => [ 'type' => 'string', 'default' => '' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
			'style'   => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'red', 'yellow', 'green' ] ],
		],
	] ) );

	// Stats Dashboard — child card (server-rendered)
	register_block_type( 'anchor/stat-card', array_merge( $block_args, [
		'title'           => 'Stat Card',
		'parent'          => [ 'anchor/stats-dashboard' ],
		'render_callback' => 'anchor_blocks_render_stat_card',
		'attributes'      => [
			'value' => [ 'type' => 'string', 'default' => '0' ],
			'label' => [ 'type' => 'string', 'default' => 'Label' ],
			'color' => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'green', 'red', 'purple', 'orange' ] ],
		],
	] ) );

	// Bar Chart — child row (server-rendered)
	register_block_type( 'anchor/bar-row', array_merge( $block_args, [
		'title'           => 'Bar Row',
		'parent'          => [ 'anchor/bar-chart' ],
		'render_callback' => 'anchor_blocks_render_bar_row',
		'attributes'      => [
			'label'   => [ 'type' => 'string', 'default' => 'Category' ],
			'value'   => [ 'type' => 'string', 'default' => '0' ],
			'percent' => [ 'type' => 'number', 'default' => 50 ],
			'color'   => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'green', 'red', 'purple', 'orange', 'teal', 'gray' ] ],
		],
	] ) );

	// IOC List — child row (server-rendered)
	register_block_type( 'anchor/ioc-row', array_merge( $block_args, [
		'title'           => 'Indicator',
		'parent'          => [ 'anchor/ioc-list' ],
		'render_callback' => 'anchor_blocks_render_ioc_row',
		'attributes'      => [
			'label' => [ 'type' => 'string', 'default' => 'Domain' ],
			'value' => [ 'type' => 'string', 'default' => '' ],
			'note'  => [ 'type' => 'string', 'default' => '' ],
			'color' => [ 'type' => 'string', 'default' => 'red', 'enum' => [ 'red', 'orange', 'blue', 'green', 'purple', 'gray' ] ],
		],
	] ) );

	// Vector Card — child (server-rendered)
	register_block_type( 'anchor/vector-card', array_merge( $block_args, [
		'title'           => 'Vector Card',
		'parent'          => [ 'anchor/vector-cards' ],
		'render_callback' => 'anchor_blocks_render_vector_card',
		'attributes'      => [
			'label'   => [ 'type' => 'string', 'default' => '' ],
			'title'   => [ 'type' => 'string', 'default' => '' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
			'detect'  => [ 'type' => 'string', 'default' => '' ],
			'color'   => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'red', 'orange', 'purple', 'green', 'gray' ] ],
		],
	] ) );

	// Term — child (server-rendered)
	register_block_type( 'anchor/term', array_merge( $block_args, [
		'title'           => 'Term',
		'parent'          => [ 'anchor/term-list' ],
		'render_callback' => 'anchor_blocks_render_term',
		'attributes'      => [
			'label'   => [ 'type' => 'string', 'default' => '' ],
			'title'   => [ 'type' => 'string', 'default' => '' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
			'color'   => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'red', 'orange', 'purple', 'green', 'gray' ] ],
		],
	] ) );

	// Report Card — server-rendered with InnerBlocks
	register_block_type( 'anchor/report-card', array_merge( $block_args, [
		'title'           => 'Report Card',
		'render_callback' => 'anchor_blocks_render_report_card',
		'attributes'      => [
			'tag'      => [ 'type' => 'string', 'default' => '' ],
			'tagColor' => [ 'type' => 'string', 'default' => 'blue', 'enum' => [ 'blue', 'green', 'red', 'purple', 'orange', 'gray' ] ],
			'title'    => [ 'type' => 'string', 'default' => '' ],
			'count'    => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	// Data Table — single server-rendered block (data lives in attributes)
	register_block_type( 'anchor/data-table', array_merge( $block_args, [
		'title'           => 'Data Table',
		'render_callback' => 'anchor_blocks_render_data_table',
		'attributes'      => [
			'title'     => [ 'type' => 'string', 'default' => '' ],
			'columns'   => [ 'type' => 'array',  'default' => [] ],
			'align'     => [ 'type' => 'array',  'default' => [] ],
			'rows'      => [ 'type' => 'array',  'default' => [] ],
			'highlight' => [ 'type' => 'number', 'default' => -1 ],
		],
	] ) );

	// Canvas — a per-post custom coded area (HTML/SVG + CSS + optional JS)
	// rendered into a declarative shadow root, with a fallback image for
	// email, RSS and any context where the shadow root never attaches.
	register_block_type( 'anchor/canvas', array_merge( $block_args, [
		'title'           => 'Canvas',
		'render_callback' => 'anchor_blocks_render_canvas',
		'attributes'      => [
			'html'        => [ 'type' => 'string',  'default' => '' ],
			'css'         => [ 'type' => 'string',  'default' => '' ],
			'js'          => [ 'type' => 'string',  'default' => '' ],
			'caption'     => [ 'type' => 'string',  'default' => '' ],
			'fallbackId'  => [ 'type' => 'number',  'default' => 0 ],
			'fallbackAlt' => [ 'type' => 'string',  'default' => '' ],
			'frame'       => [ 'type' => 'boolean', 'default' => true ],
		],
	] ) );

	// Audio — a player card with a waveform you scrub and section markers you
	// jump to. A real <audio> element does the playing; the waveform and the
	// sections are stored on the block (peaks 0-99, one "m:ss Label" a line),
	// so nothing has to read the file at page load.
	register_block_type( 'anchor/audio', array_merge( $block_args, [
		'title'           => 'Audio',
		'render_callback' => 'anchor_blocks_render_audio',
		'view_script'     => 'anchor-blocks-audio',
		'attributes'      => [
			'src'      => [ 'type' => 'string', 'default' => '' ],
			'title'    => [ 'type' => 'string', 'default' => '' ],
			'meta'     => [ 'type' => 'string', 'default' => '' ],
			'link'     => [ 'type' => 'string', 'default' => '' ],
			'caption'  => [ 'type' => 'string', 'default' => '' ],
			'duration' => [ 'type' => 'number', 'default' => 0 ],
			'peaks'    => [ 'type' => 'string', 'default' => '' ],
			'sections' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );
} );

// Ensure block styles load inside the editor iframe
add_action( 'enqueue_block_editor_assets', function() {
	wp_enqueue_style( 'anchor-blocks-style' );
	wp_enqueue_style( 'anchor-blocks-editor-style' );
} );

// Inline-style Anchor Blocks in outgoing emails
add_filter( 'wp_mail', 'anchor_blocks_inline_email_styles' );

function anchor_blocks_inline_email_styles( $args ) {
	$html = $args['message'];
	if ( strpos( $html, 'wp-block-anchor-' ) === false && strpos( $html, 'badge' ) === false ) {
		return $args;
	}

	// Canvas — email clients ignore shadow DOM, so drop the live code and
	// leave the light-DOM fallback image, then reveal the "view on the web" link.
	if ( strpos( $html, 'wp-block-anchor-canvas' ) !== false ) {
		$html = preg_replace( '#<template shadowrootmode="open">[\s\S]*?</template><!--/ab-canvas-->#', '', $html );
		$html = preg_replace( '#<script class="ab-canvas-script">[\s\S]*?</script>#', '', $html );
		$html = str_replace( ' hidden class="ab-canvas-weblink"', ' class="ab-canvas-weblink"', $html );
		$html = preg_replace( '/<figure class="wp-block-anchor-canvas[^"]*"/', '<figure class="wp-block-anchor-canvas" style="margin:1.5rem 0"', $html );
		$html = str_replace( 'class="ab-canvas-caption"', 'class="ab-canvas-caption" style="margin-top:0.6rem;font-size:0.85rem;line-height:1.5;color:#6b7f94;text-align:center"', $html );
		$html = str_replace( 'class="ab-canvas-weblink"', 'class="ab-canvas-weblink" style="display:block;margin-top:0.35rem;font-size:0.85rem;text-align:center;color:#2b8fc7"', $html );
	}

	$map = [
		// Conversation
		'wp-block-anchor-conversation"' =>
			'wp-block-anchor-conversation" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(26,39,68,0.06);margin:1.5rem 0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif"',
		'ab-conv-header"' =>
			'ab-conv-header" style="background:#1a2744;color:#fff;padding:0.6rem 1.25rem;font-size:0.78rem;font-weight:600;font-family:\'SF Mono\',\'Fira Code\',monospace"',

		// Conversation Messages
		'wp-block-anchor-conversation-message is-role-user"' =>
			'wp-block-anchor-conversation-message is-role-user" style="padding:1rem 1.25rem;border-bottom:1px solid #e4e9f0;font-size:0.9rem;line-height:1.65;color:#2d3f52;background:#f8fafc"',
		'wp-block-anchor-conversation-message is-role-assistant"' =>
			'wp-block-anchor-conversation-message is-role-assistant" style="padding:1rem 1.25rem;border-bottom:1px solid #e4e9f0;font-size:0.9rem;line-height:1.65;color:#2d3f52;background:#fff"',
		'ab-msg-label user"' =>
			'ab-msg-label user" style="font-family:\'SF Mono\',monospace;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;color:#55c1e7"',
		'ab-msg-label assistant"' =>
			'ab-msg-label assistant" style="font-family:\'SF Mono\',monospace;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;color:#9333ea"',

		// Timeline — no padding/border here, handled via table rebuild below
		'wp-block-anchor-timeline"' =>
			'wp-block-anchor-timeline" style="margin:1.5rem 0"',

		// Callout
		'wp-block-anchor-callout is-style-blue"' =>
			'wp-block-anchor-callout is-style-blue" style="border-radius:12px;padding:1.15rem 1.35rem;margin:1.25rem 0;font-size:0.9rem;line-height:1.6;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af"',
		'wp-block-anchor-callout is-style-red"' =>
			'wp-block-anchor-callout is-style-red" style="border-radius:12px;padding:1.15rem 1.35rem;margin:1.25rem 0;font-size:0.9rem;line-height:1.6;background:#fef2f2;border:1px solid #fecaca;color:#991b1b"',
		'wp-block-anchor-callout is-style-green"' =>
			'wp-block-anchor-callout is-style-green" style="border-radius:12px;padding:1.15rem 1.35rem;margin:1.25rem 0;font-size:0.9rem;line-height:1.6;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534"',
		'wp-block-anchor-callout is-style-yellow"' =>
			'wp-block-anchor-callout is-style-yellow" style="border-radius:12px;padding:1.15rem 1.35rem;margin:1.25rem 0;font-size:0.9rem;line-height:1.6;background:#fffbeb;border:1px solid #fde68a;color:#92400e"',
		'ab-callout-title"' =>
			'ab-callout-title" style="font-weight:700;margin-bottom:0.25rem"',

		// Stats Dashboard
		'wp-block-anchor-stats-dashboard"' =>
			'wp-block-anchor-stats-dashboard" style="display:flex;gap:12px;margin:1.5rem 0"',

		// Stat Cards
		'wp-block-anchor-stat-card is-color-blue"' =>
			'wp-block-anchor-stat-card is-color-blue" style="background:#fff;border-radius:12px;padding:1.5rem 1.15rem 1.25rem;text-align:center;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',
		'wp-block-anchor-stat-card is-color-green"' =>
			'wp-block-anchor-stat-card is-color-green" style="background:#fff;border-radius:12px;padding:1.5rem 1.15rem 1.25rem;text-align:center;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',
		'wp-block-anchor-stat-card is-color-red"' =>
			'wp-block-anchor-stat-card is-color-red" style="background:#fff;border-radius:12px;padding:1.5rem 1.15rem 1.25rem;text-align:center;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',
		'wp-block-anchor-stat-card is-color-purple"' =>
			'wp-block-anchor-stat-card is-color-purple" style="background:#fff;border-radius:12px;padding:1.5rem 1.15rem 1.25rem;text-align:center;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',
		'wp-block-anchor-stat-card is-color-orange"' =>
			'wp-block-anchor-stat-card is-color-orange" style="background:#fff;border-radius:12px;padding:1.5rem 1.15rem 1.25rem;text-align:center;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',

		// Bar Chart
		'wp-block-anchor-bar-chart"' =>
			'wp-block-anchor-bar-chart" style="background:#fff;border-radius:12px;padding:1.5rem;margin:1.5rem 0;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',
		'ab-chart-title"' =>
			'ab-chart-title" style="font-size:0.85rem;font-weight:650;margin-bottom:1rem"',

		// Badges
		'badge red"'    => 'badge red" style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:700;font-family:\'SF Mono\',monospace;text-transform:uppercase;letter-spacing:0.03em;background:#fef2f2;color:#dc2626"',
		'badge green"'  => 'badge green" style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:700;font-family:\'SF Mono\',monospace;text-transform:uppercase;letter-spacing:0.03em;background:#dcfce7;color:#16a34a"',
		'badge blue"'   => 'badge blue" style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:700;font-family:\'SF Mono\',monospace;text-transform:uppercase;letter-spacing:0.03em;background:#eff6ff;color:#2563eb"',
		'badge orange"' => 'badge orange" style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:700;font-family:\'SF Mono\',monospace;text-transform:uppercase;letter-spacing:0.03em;background:#fff7ed;color:#ea580c"',
	];

	foreach ( $map as $find => $replace ) {
		$html = str_replace( $find, $replace, $html );
	}

	// Data Table — inline-style its own classes BEFORE the generic table rules
	// below, so the style= attribute makes the generic <td> rule skip these cells.
	if ( strpos( $html, 'wp-block-anchor-data-table' ) !== false ) {
		$html = str_replace(
			'class="wp-block-anchor-data-table"',
			'class="wp-block-anchor-data-table" style="background:#fff;border-radius:12px;padding:1.5rem;margin:1.5rem 0;box-shadow:0 1px 3px rgba(26,39,68,0.06)"',
			$html
		);
		$html = str_replace(
			'class="ab-dt-title"',
			'class="ab-dt-title" style="font-size:0.85rem;font-weight:650;margin-bottom:1rem"',
			$html
		);
		$html = str_replace(
			'class="ab-dt-table"',
			'class="ab-dt-table" style="width:100%;border-collapse:collapse"',
			$html
		);
		$html = preg_replace(
			'/<th class="ab-dt-th align-(left|right|center)">/',
			'<th style="text-align:$1;padding:0.55rem 0.9rem;border-bottom:2px solid #1a2744;font-family:\'SF Mono\',monospace;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.03em;color:#6b7f94">',
			$html
		);
		$html = preg_replace(
			'/<td class="ab-dt-td align-(left|right|center)">/',
			'<td style="text-align:$1;padding:0.5rem 0.9rem;border-bottom:1px solid #e4e9f0;font-family:\'SF Mono\',monospace;font-size:0.82rem;color:#2d3f52">',
			$html
		);
		$html = str_replace(
			'<tr class="ab-dt-tr is-highlight">',
			'<tr class="ab-dt-tr" style="background:#f8fafc;font-weight:700;color:#1a2744">',
			$html
		);
		$html = str_replace( '<tr class="ab-dt-tr">', '<tr>', $html );
	}

	// Style WordPress block tables BEFORE timeline rebuild (so timeline cells aren't affected)
	$html = str_replace(
		'<table class="has-fixed-layout">',
		'<table class="has-fixed-layout" style="width:100%;border-collapse:collapse;margin:1rem 0;font-size:0.9rem">',
		$html
	);
	$html = preg_replace( '/<th>/', '<th style="text-align:left;padding:0.5rem 0.75rem;border-bottom:2px solid #1a2744;font-weight:700">', $html );
	$html = preg_replace(
		'/<td(?=[>\s])(?![^>]*style=)([^>]*)>/',
		'<td$1 style="padding:0.5rem 0.75rem;border-bottom:1px solid #e4e9f0">',
		$html
	);
	$html = preg_replace(
		'/<td([^>]*) style="([^"]*)"/',
		'<td$1 style="$2;padding:0.5rem 0.75rem;border-bottom:1px solid #e4e9f0"',
		$html
	);

	// Audio — no player in email: a card that links out to the song.
	// Built after the generic cell styling above so its cells keep their own padding.
	if ( strpos( $html, 'wp-block-anchor-audio' ) !== false ) {
		$html = preg_replace_callback( '#<figure class="wp-block-anchor-audio"([^>]*)>([\s\S]*?)</figure>#', 'anchor_blocks_email_audio', $html );
	}

	// Rebuild entire timeline block as a single table (continuous vertical line + dots)
	$dot_colors = [
		'red'    => '#dc2626',
		'green'  => '#16a34a',
		'blue'   => '#2563eb',
		'orange' => '#ea580c',
	];
	$html = preg_replace_callback(
		'/<div[^>]*wp-block-anchor-timeline"[^>]*>(\s*(?:<div[^>]*wp-block-anchor-timeline-item[^>]*>\s*<div[^>]*ab-tl-date[^>]*>.*?<\/div>\s*<div[^>]*ab-tl-text[^>]*>.*?<\/div>\s*<\/div>\s*)+)<\/div>/s',
		function( $timeline ) use ( $dot_colors ) {
			$rows = '';
			preg_match_all(
				'/<div[^>]*is-color-(red|green|blue|orange)"[^>]*>\s*<div[^>]*ab-tl-date[^>]*>(.*?)<\/div>\s*<div[^>]*ab-tl-text[^>]*>(.*?)<\/div>\s*<\/div>/s',
				$timeline[1], $items, PREG_SET_ORDER
			);
			$total = count( $items );
			$rows  = '';
			$line  = 'border-right:2px solid #e4e9f0;';
			$spacer = '<td style="width:8px;' . $line . 'font-size:1px;line-height:1px;color:transparent">&zwnj;</td>';

			foreach ( $items as $i => $m ) {
				$hex     = $dot_colors[ $m[1] ];
				$date    = $m[2];
				$text    = $m[3];
				$is_last = ( $i === $total - 1 );

				// Connector line from previous item
				if ( $i > 0 ) {
					$rows .= '<tr>' . $spacer . '<td style="height:16px"></td></tr>';
				}

				// Dot + date row (colspan so dot is free from column width)
				$rows .= '<tr><td colspan="2" style="padding:0 0 2px 1px">'
					. '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . $hex . ';border:2px solid #fff;vertical-align:middle;margin-right:6px"></span>'
					. '<span style="font-family:\'SF Mono\',monospace;font-size:0.82rem;color:#6b7f94;vertical-align:middle">' . $date . '</span>'
					. '</td></tr>';

				// Text row with line
				$line_cell = $is_last
					? '<td style="width:8px;font-size:1px;line-height:1px;color:transparent">&zwnj;</td>'
					: $spacer;
				$rows .= '<tr>' . $line_cell
					. '<td style="vertical-align:top;padding:0 0 0 13px">'
					. '<div style="font-size:0.95rem;color:#2d3f52;line-height:1.6">' . $text . '</div>'
					. '</td></tr>';
			}

			return '<table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:1.5rem 0;width:100%">'
				. $rows . '</table>';
		},
		$html
	);

	// Make all content images full-width in emails and strip srcset/sizes
	$html = preg_replace( '/\s+srcset="[^"]*"/', '', $html );
	$html = preg_replace( '/\s+sizes="[^"]*"/', '', $html );
	$html = preg_replace(
		'/<img ([^>]*?)\/?>/',
		'<img $1 style="max-width:100%;height:auto;display:block" />',
		$html
	);

	// Rebuild bar-chart blocks as email-safe tables
	$bar_colors = [
		'blue'   => '#2b8fc7',
		'green'  => '#16a34a',
		'red'    => '#dc2626',
		'purple' => '#8b5cf6',
		'orange' => '#d97706',
		'teal'   => '#0e8a8a',
		'gray'   => '#6b7280',
	];
	$html = preg_replace_callback(
		'/<div[^>]*wp-block-anchor-bar-chart"[^>]*>([\s\S]*?)\n<\/div>\n/s',
		function( $chart ) use ( $bar_colors ) {
			$inner = $chart[1];

			// Extract title if present
			$title_html = '';
			if ( preg_match( '/<div[^>]*ab-chart-title[^>]*>(.*?)<\/div>/s', $inner, $t ) ) {
				$title_html = '<tr><td colspan="2" style="padding:0 0 12px 0;font-size:0.85rem;font-weight:650">' . $t[1] . '</td></tr>';
			}

			// Extract bar rows
			$rows = '';
			preg_match_all(
				'/<div[^>]*wp-block-anchor-bar-row[^>]*>\s*<div[^>]*ab-bar-label[^>]*>(.*?)<\/div>\s*<div[^>]*ab-bar-track[^>]*>\s*<div[^>]*is-color-(\w+)"[^>]*style="width:(\d+)%"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s',
				$inner, $items, PREG_SET_ORDER
			);

			foreach ( $items as $m ) {
				$label   = $m[1];
				$color   = $bar_colors[ $m[2] ] ?? '#2b8fc7';
				$percent = max( intval( $m[3] ), 5 );
				$value   = $m[4];

				$rows .= '<tr>'
					. '<td style="padding:4px 12px 4px 0;font-size:0.78rem;color:#6b7f94;text-align:right;white-space:nowrap;vertical-align:middle">' . $label . '</td>'
					. '<td style="padding:4px 0;width:100%;vertical-align:middle">'
					. '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse"><tr>'
					. '<td style="width:' . $percent . '%;background:' . $color . ';border-radius:6px;padding:4px 8px;font-size:0.68rem;font-weight:700;color:#fff;font-family:\'SF Mono\',monospace;white-space:nowrap">' . $value . '</td>'
					. '<td style="width:' . ( 100 - $percent ) . '%"></td>'
					. '</tr></table>'
					. '</td></tr>';
			}

			return '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:1.5rem 0">'
				. $title_html . $rows . '</table>' . "\n";
		},
		$html
	);

	// Rebuild stat-card values with color
	$stat_value_colors = [
		'blue'   => '#2b8fc7',
		'green'  => '#16a34a',
		'red'    => '#dc2626',
		'purple' => '#8b5cf6',
		'orange' => '#d97706',
	];
	foreach ( $stat_value_colors as $name => $hex ) {
		$html = str_replace(
			'wp-block-anchor-stat-card is-color-' . $name . '"',
			'wp-block-anchor-stat-card is-color-' . $name . '" data-color="' . $hex . '"',
			$html
		);
	}
	// Inline stat-value colors
	$html = preg_replace_callback(
		'/data-color="(#[0-9a-f]+)"[^>]*>.*?<div[^>]*ab-stat-value"/',
		function( $m ) {
			return str_replace( 'ab-stat-value"', 'ab-stat-value" style="font-size:2.4rem;font-weight:800;letter-spacing:-0.03em;line-height:1;margin-bottom:0.3rem;color:' . $m[1] . '"', $m[0] );
		},
		$html
	);
	// Inline stat-label
	$html = str_replace(
		'ab-stat-label"',
		'ab-stat-label" style="font-size:0.78rem;color:#6b7f94;font-weight:500"'
	, $html );

	// Rebuild IOC lists as email-safe tables
	$ioc_colors = [
		'red'    => '#dc2626',
		'orange' => '#d97706',
		'blue'   => '#2b8fc7',
		'green'  => '#16a34a',
		'purple' => '#8b5cf6',
		'gray'   => '#6b7280',
	];
	$html = preg_replace_callback(
		'/<div[^>]*wp-block-anchor-ioc-list"[^>]*>([\s\S]*?)\n<\/div>\n/s',
		function( $list ) use ( $ioc_colors ) {
			$inner = $list[1];

			$title_html = '';
			if ( preg_match( '/<div[^>]*ab-ioc-title[^>]*>(.*?)<\/div>/s', $inner, $t ) ) {
				$title_html = '<tr><td colspan="2" style="padding:0 0 12px 0;font-size:0.85rem;font-weight:650">' . $t[1] . '</td></tr>';
			}

			$rows = '';
			preg_match_all(
				'/<div[^>]*wp-block-anchor-ioc-row"[^>]*>\s*<span[^>]*is-color-(\w+)"[^>]*>(.*?)<\/span>\s*<code[^>]*ab-ioc-value"[^>]*>(.*?)<\/code>\s*(?:<span[^>]*ab-ioc-note"[^>]*>(.*?)<\/span>)?\s*<\/div>/s',
				$inner, $items, PREG_SET_ORDER
			);

			foreach ( $items as $m ) {
				$hex   = $ioc_colors[ $m[1] ] ?? '#dc2626';
				$label = $m[2];
				$value = $m[3];
				$note  = $m[4] ?? '';

				$note_html = $note
					? '<div style="font-size:0.8rem;color:#6b7f94;margin-top:2px">' . $note . '</div>'
					: '';

				$rows .= '<tr>'
					. '<td style="padding:6px 12px 6px 0;vertical-align:top;white-space:nowrap">'
					. '<span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:0.68rem;font-weight:700;font-family:\'SF Mono\',monospace;text-transform:uppercase;letter-spacing:0.03em;color:#fff;background:' . $hex . '">' . $label . '</span>'
					. '</td>'
					. '<td style="padding:6px 0;vertical-align:top;width:100%;border-bottom:1px solid #eef2f7">'
					. '<code style="font-family:\'SF Mono\',monospace;font-size:0.82rem;color:#1a2744;background:#f5f8fb;padding:2px 7px;border-radius:5px;word-break:break-all">' . $value . '</code>'
					. $note_html
					. '</td></tr>';
			}

			return '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background:#fff;border-radius:12px;margin:1.5rem 0">'
				. $title_html . $rows . '</table>' . "\n";
		},
		$html
	);

	// Rebuild vector-cards as email-safe stacked cards
	$vc_colors = [
		'blue'   => '#2b8fc7',
		'red'    => '#dc2626',
		'orange' => '#d97706',
		'purple' => '#8b5cf6',
		'green'  => '#16a34a',
		'gray'   => '#6b7280',
	];
	$html = preg_replace_callback(
		'/<div[^>]*wp-block-anchor-vector-cards"[^>]*>([\s\S]*?)\n<\/div>\n/s',
		function( $cards ) use ( $vc_colors ) {
			$inner = $cards[1];

			$heading_html = '';
			if ( preg_match( '/<div[^>]*ab-vc-heading[^>]*>(.*?)<\/div>/s', $inner, $h ) ) {
				$heading_html = '<tr><td style="padding:0 0 12px 0;font-size:0.85rem;font-weight:650">' . $h[1] . '</td></tr>';
			}

			$rows = '';
			preg_match_all(
				'/<div[^>]*wp-block-anchor-vector-card is-color-(\w+)"[^>]*>\s*<div[^>]*ab-vc-head[^>]*>(?:<span[^>]*ab-vc-label[^>]*>(.*?)<\/span>)?(?:<div[^>]*ab-vc-title[^>]*>(.*?)<\/div>)?<\/div>\s*<div[^>]*ab-vc-body[^>]*>([\s\S]*?)<\/div>\s*(?:<div[^>]*ab-vc-detect[^>]*>.*?ab-vc-detect-text"[^>]*>(.*?)<\/span>.*?<\/div>)?\s*<\/div>/s',
				$inner, $items, PREG_SET_ORDER
			);

			foreach ( $items as $m ) {
				$accent = $vc_colors[ $m[1] ] ?? '#2b8fc7';
				$label  = $m[2] ?? '';
				$title  = $m[3] ?? '';
				$body   = $m[4] ?? '';
				$detect = $m[5] ?? '';

				$label_html = $label
					? '<span style="display:inline-block;font-family:\'SF Mono\',monospace;font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;padding:3px 8px;border-radius:6px;color:#fff;background:' . $accent . '">' . $label . '</span>'
					: '';
				$title_html = $title
					? '<div style="font-size:1rem;font-weight:650;color:#1a2744;margin-top:' . ( $label ? '8px' : '0' ) . '">' . $title . '</div>'
					: '';
				$detect_html = $detect
					? '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e4e9f0;font-size:0.8rem;color:#6b7f94;line-height:1.5"><span style="font-family:\'SF Mono\',monospace;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#16a34a">Detect &mdash; </span>' . $detect . '</div>'
					: '';

				$rows .= '<tr><td style="background:#fff;border-radius:12px;border-top:3px solid ' . $accent . ';padding:16px 18px;box-shadow:0 1px 3px rgba(26,39,68,0.06)">'
					. $label_html
					. $title_html
					. '<div style="font-size:0.86rem;color:#3d5166;line-height:1.6;margin-top:8px">' . $body . '</div>'
					. $detect_html
					. '</td></tr><tr><td style="height:10px;font-size:1px;line-height:1px">&nbsp;</td></tr>';
			}

			return '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:1.5rem 0">'
				. $heading_html . $rows . '</table>' . "\n";
		},
		$html
	);

	// Rebuild term-list as an email-safe single card
	$term_colors = [
		'blue'   => '#2b8fc7',
		'red'    => '#dc2626',
		'orange' => '#d97706',
		'purple' => '#8b5cf6',
		'green'  => '#16a34a',
		'gray'   => '#6b7280',
	];
	$html = preg_replace_callback(
		'/<div[^>]*wp-block-anchor-term-list"[^>]*>([\s\S]*?)\n<\/div>\n/s',
		function( $cards ) use ( $term_colors ) {
			$inner = $cards[1];

			$heading_html = '';
			if ( preg_match( '/<div[^>]*ab-termlist-heading[^>]*>(.*?)<\/div>/s', $inner, $h ) ) {
				$heading_html = '<tr><td style="padding:0 0 12px 0;font-size:0.85rem;font-weight:650">' . $h[1] . '</td></tr>';
			}

			$rows  = '';
			$first = true;
			preg_match_all(
				'/<div[^>]*wp-block-anchor-term is-color-(\w+)"[^>]*>(?:<div[^>]*ab-term-label[^>]*>(.*?)<\/div>)?(?:<div[^>]*ab-term-title[^>]*>(.*?)<\/div>)?<div[^>]*ab-term-body[^>]*>([\s\S]*?)<\/div>\s*<\/div>/s',
				$inner, $items, PREG_SET_ORDER
			);

			foreach ( $items as $m ) {
				$accent = $term_colors[ $m[1] ] ?? '#2b8fc7';
				$label  = $m[2] ?? '';
				$title  = $m[3] ?? '';
				$body   = $m[4] ?? '';

				$border = $first ? '' : 'border-top:1px solid #e4e9f0;';
				$first  = false;

				$label_html = $label
					? '<div style="font-size:1.5rem;font-weight:800;line-height:1;color:' . $accent . ';margin-bottom:10px">' . $label . '</div>'
					: '';
				$title_html = $title
					? '<div style="font-size:1rem;font-weight:700;color:#1a2744;margin-bottom:8px">' . $title . '</div>'
					: '';

				$rows .= '<tr><td style="padding:22px 0;' . $border . '">'
					. $label_html
					. $title_html
					. '<div style="font-size:0.9rem;color:#3d5166;line-height:1.7">' . $body . '</div>'
					. '</td></tr>';
			}

			return '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background:#fff;border-radius:12px;margin:1.5rem 0">'
				. '<tr><td style="padding:0 24px">'
				. '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">'
				. $heading_html . $rows . '</table></td></tr></table>' . "\n";
		},
		$html
	);

	$args['message'] = $html;
	return $args;
}

function anchor_blocks_render_conversation_message( $attrs ) {
	$role    = esc_attr( $attrs['role'] ?? 'user' );
	$label   = esc_html( $attrs['label'] ?? 'Austin' );
	$content = wp_kses_post( $attrs['content'] ?? '' );

	return sprintf(
		'<div class="wp-block-anchor-conversation-message is-role-%s"><div class="ab-msg-label %s">%s</div><div class="ab-msg-content">%s</div></div>',
		$role, $role, $label, $content
	);
}

function anchor_blocks_render_timeline_item( $attrs ) {
	$color   = esc_attr( $attrs['color'] ?? 'blue' );
	$date    = wp_kses_post( $attrs['date'] ?? '' );
	$content = wp_kses_post( $attrs['content'] ?? '' );

	return sprintf(
		'<div class="wp-block-anchor-timeline-item is-color-%s"><div class="ab-tl-date">%s</div><div class="ab-tl-text">%s</div></div>',
		$color, $date, $content
	);
}

function anchor_blocks_render_callout( $attrs ) {
	$style   = esc_attr( $attrs['style'] ?? 'blue' );
	$title   = wp_kses_post( $attrs['title'] ?? '' );
	$content = wp_kses_post( $attrs['content'] ?? '' );

	$title_html = $title ? sprintf( '<div class="ab-callout-title">%s</div>', $title ) : '';

	return sprintf(
		'<div class="wp-block-anchor-callout is-style-%s">%s<div class="ab-callout-content">%s</div></div>',
		$style, $title_html, $content
	);
}

function anchor_blocks_render_stats_dashboard( $attrs, $content ) {
	return sprintf(
		'<div class="wp-block-anchor-stats-dashboard">%s</div>',
		$content
	);
}

function anchor_blocks_render_bar_chart( $attrs, $content ) {
	$title = esc_html( $attrs['title'] ?? '' );
	$title_html = $title ? sprintf( '<div class="ab-chart-title">%s</div>', $title ) : '';

	return sprintf(
		'<div class="wp-block-anchor-bar-chart">%s%s</div>',
		$title_html, $content
	);
}

function anchor_blocks_render_data_table( $attrs ) {
	$title     = $attrs['title'] ?? '';
	$columns   = is_array( $attrs['columns'] ?? null ) ? $attrs['columns'] : [];
	$align     = is_array( $attrs['align'] ?? null ) ? $attrs['align'] : [];
	$rows      = is_array( $attrs['rows'] ?? null ) ? $attrs['rows'] : [];
	$highlight = intval( $attrs['highlight'] ?? -1 );

	$align_of = function( $i ) use ( $align ) {
		$a = $align[ $i ] ?? 'left';
		return in_array( $a, [ 'left', 'right', 'center' ], true ) ? $a : 'left';
	};

	$thead = '';
	foreach ( $columns as $i => $c ) {
		$thead .= sprintf( '<th class="ab-dt-th align-%s">%s</th>', $align_of( $i ), esc_html( $c ) );
	}

	$tbody = '';
	foreach ( $rows as $ri => $row ) {
		$cells = '';
		foreach ( (array) $row as $ci => $cell ) {
			$cells .= sprintf( '<td class="ab-dt-td align-%s">%s</td>', $align_of( $ci ), esc_html( $cell ) );
		}
		$tbody .= sprintf(
			'<tr class="ab-dt-tr%s">%s</tr>',
			$ri === $highlight ? ' is-highlight' : '',
			$cells
		);
	}

	$title_html = $title !== '' ? sprintf( '<div class="ab-dt-title">%s</div>', esc_html( $title ) ) : '';

	return sprintf(
		'<div class="wp-block-anchor-data-table">%s<table class="ab-dt-table"><thead><tr>%s</tr></thead><tbody>%s</tbody></table></div>',
		$title_html, $thead, $tbody
	);
}

function anchor_blocks_render_ioc_list( $attrs, $content ) {
	$title = esc_html( $attrs['title'] ?? '' );
	$title_html = $title ? sprintf( '<div class="ab-ioc-title">%s</div>', $title ) : '';

	return sprintf(
		'<div class="wp-block-anchor-ioc-list">%s%s</div>',
		$title_html, $content
	);
}

function anchor_blocks_render_ioc_row( $attrs ) {
	$label = esc_html( $attrs['label'] ?? 'Domain' );
	$color = esc_attr( $attrs['color'] ?? 'red' );
	$value = esc_html( $attrs['value'] ?? '' );
	$note  = wp_kses_post( $attrs['note'] ?? '' );

	$note_html = $note ? sprintf( '<span class="ab-ioc-note">%s</span>', $note ) : '';

	return sprintf(
		'<div class="wp-block-anchor-ioc-row"><span class="ab-ioc-type is-color-%s">%s</span><code class="ab-ioc-value">%s</code>%s</div>',
		$color, $label, $value, $note_html
	);
}

function anchor_blocks_render_vector_cards( $attrs, $content ) {
	$title = esc_html( $attrs['title'] ?? '' );
	$title_html = $title ? sprintf( '<div class="ab-vc-heading">%s</div>', $title ) : '';

	return sprintf(
		'<div class="wp-block-anchor-vector-cards">%s%s</div>',
		$title_html, $content
	);
}

function anchor_blocks_render_vector_card( $attrs ) {
	$label   = esc_html( $attrs['label'] ?? '' );
	$color   = esc_attr( $attrs['color'] ?? 'blue' );
	$title   = wp_kses_post( $attrs['title'] ?? '' );
	$content = wp_kses_post( $attrs['content'] ?? '' );
	$detect  = wp_kses_post( $attrs['detect'] ?? '' );

	$label_html  = $label ? sprintf( '<span class="ab-vc-label is-color-%s">%s</span>', $color, $label ) : '';
	$title_html  = $title ? sprintf( '<div class="ab-vc-title">%s</div>', $title ) : '';
	$detect_html = $detect
		? sprintf( '<div class="ab-vc-detect"><span class="ab-vc-detect-label">Detect</span><span class="ab-vc-detect-text">%s</span></div>', $detect )
		: '';

	return sprintf(
		'<div class="wp-block-anchor-vector-card is-color-%s"><div class="ab-vc-head">%s%s</div><div class="ab-vc-body">%s</div>%s</div>',
		$color, $label_html, $title_html, $content, $detect_html
	);
}

function anchor_blocks_render_term_list( $attrs, $content ) {
	$title = esc_html( $attrs['title'] ?? '' );
	$title_html = $title ? sprintf( '<div class="ab-termlist-heading">%s</div>', $title ) : '';

	return sprintf(
		'<div class="wp-block-anchor-term-list">%s%s</div>',
		$title_html, $content
	);
}

function anchor_blocks_render_term( $attrs ) {
	$label   = wp_kses_post( $attrs['label'] ?? '' );
	$color   = esc_attr( $attrs['color'] ?? 'blue' );
	$title   = wp_kses_post( $attrs['title'] ?? '' );
	$content = wp_kses_post( $attrs['content'] ?? '' );

	$label_html = $label ? sprintf( '<div class="ab-term-label is-color-%s">%s</div>', $color, $label ) : '';
	$title_html = $title ? sprintf( '<div class="ab-term-title">%s</div>', $title ) : '';

	return sprintf(
		'<div class="wp-block-anchor-term is-color-%s">%s%s<div class="ab-term-body">%s</div></div>',
		$color, $label_html, $title_html, $content
	);
}

function anchor_blocks_render_stat_card( $attrs ) {
	$value = esc_html( $attrs['value'] ?? '0' );
	$label = esc_html( $attrs['label'] ?? 'Label' );
	$color = esc_attr( $attrs['color'] ?? 'blue' );

	return sprintf(
		'<div class="wp-block-anchor-stat-card is-color-%s"><div class="ab-stat-value">%s</div><div class="ab-stat-label">%s</div></div>',
		$color, $value, $label
	);
}

function anchor_blocks_render_bar_row( $attrs ) {
	$label   = esc_html( $attrs['label'] ?? 'Category' );
	$value   = esc_html( $attrs['value'] ?? '0' );
	$percent = min( 100, max( 1, intval( $attrs['percent'] ?? 50 ) ) );
	$color   = esc_attr( $attrs['color'] ?? 'blue' );

	return sprintf(
		'<div class="wp-block-anchor-bar-row"><div class="ab-bar-label">%s</div><div class="ab-bar-track"><div class="ab-bar-fill is-color-%s" style="width:%d%%">%s</div></div></div>',
		$label, $color, $percent, $value
	);
}

function anchor_blocks_render_report_card( $attrs, $content ) {
	$tag       = esc_html( $attrs['tag'] ?? '' );
	$tag_color = esc_attr( $attrs['tagColor'] ?? 'blue' );
	$title     = esc_html( $attrs['title'] ?? '' );
	$count     = esc_html( $attrs['count'] ?? '' );

	$header = '';
	if ( $tag || $title || $count ) {
		$tag_html   = $tag ? sprintf( '<span class="ab-rc-tag is-color-%s">%s</span>', $tag_color, $tag ) : '';
		$title_html = $title ? sprintf( '<div class="ab-rc-title">%s</div>', $title ) : '';
		$count_html = $count ? sprintf( '<span class="ab-rc-count">%s</span>', $count ) : '';
		$header = sprintf( '<div class="ab-rc-header">%s%s%s</div>', $tag_html, $title_html, $count_html );
	}

	return sprintf(
		'<div class="wp-block-anchor-report-card">%s<div class="ab-rc-body">%s</div></div>',
		$header, $content
	);
}

function anchor_blocks_render_canvas( $attrs ) {
	$html     = (string) ( $attrs['html'] ?? '' );
	$css      = (string) ( $attrs['css'] ?? '' );
	$js       = (string) ( $attrs['js'] ?? '' );
	$caption  = wp_kses_post( $attrs['caption'] ?? '' );
	$fallback = (int) ( $attrs['fallbackId'] ?? 0 );
	$alt      = (string) ( $attrs['fallbackAlt'] ?? '' );
	$framed   = ! empty( $attrs['frame'] ?? true );

	// Raw code only renders for authors who may post unfiltered HTML.
	// Everyone else gets the fallback image and caption.
	$post    = get_post();
	$trusted = $post ? user_can( (int) $post->post_author, 'unfiltered_html' ) : current_user_can( 'unfiltered_html' );
	$live    = $trusted && ! is_feed() && ( '' !== trim( $html ) || '' !== trim( $css ) );

	$fallback_html = '';
	if ( $fallback ) {
		$fallback_html = wp_get_attachment_image( $fallback, 'full', false, [
			'class'   => 'ab-canvas-fallback',
			'alt'     => $alt,
			'loading' => 'lazy',
		] );
	}

	$shadow = '';
	if ( $live ) {
		// Shared base: tokens inherit across the shadow boundary, so the
		// code can use var(--ab-*) / the theme's vars and follow dark mode.
		$base   = ':host{display:block;color:var(--ab-text);line-height:1.5;container-type:inline-size}'
			. '*,*::before,*::after{box-sizing:border-box}img,svg{max-width:100%;height:auto}';
		$shadow = '<template shadowrootmode="open"><style>' . $base . '</style>'
			. ( '' !== trim( $css ) ? '<style>' . $css . '</style>' : '' )
			. $html . '</template><!--/ab-canvas-->';
	}

	$script = '';
	if ( $live && '' !== trim( $js ) ) {
		// Runs once per block with `root` (the shadow root) and `host` in scope.
		$script = '<script class="ab-canvas-script">(function(){var s=document.currentScript,h=s&&s.previousElementSibling;if(!h)return;'
			. '(function(root,host){' . str_ireplace( '</script', '<\/script', $js ) . "\n}).call(h,h.shadowRoot||h,h);})();</script>";
	}

	$weblink = '';
	if ( $live && $post ) {
		$weblink = sprintf( '<a hidden class="ab-canvas-weblink" href="%s">View the interactive version on the web</a>', esc_url( get_permalink( $post ) ) );
	}

	$caption_html = $caption ? sprintf( '<figcaption class="ab-canvas-caption">%s</figcaption>', $caption ) : '';

	return sprintf(
		'<figure class="wp-block-anchor-canvas%s"><div class="ab-canvas-stage"><div class="ab-canvas-host">%s%s</div>%s</div>%s%s</figure>',
		$framed ? ' is-framed' : '',
		$shadow,
		$fallback_html,
		$script,
		$caption_html,
		$weblink
	);
}

/**
 * "m:ss" (or "h:mm:ss", or plain seconds) to seconds. Decimals allowed.
 */
function anchor_blocks_audio_seconds( $stamp ) {
	$seconds = 0.0;
	foreach ( explode( ':', trim( $stamp ) ) as $part ) {
		$seconds = $seconds * 60 + (float) $part;
	}
	return $seconds;
}

function anchor_blocks_audio_time( $seconds ) {
	$seconds = max( 0, (int) floor( $seconds ) );
	return sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
}

/**
 * Sections are one per line, "1:16 Drop". Returns [ [ 'start' => 76.0, 'name' => 'Drop' ], … ] sorted by start.
 */
function anchor_blocks_audio_sections( $text ) {
	$sections = [];
	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		if ( preg_match( '/^\s*(\d+(?::\d+){0,2}(?:\.\d+)?)\s+(.+?)\s*$/', $line, $m ) ) {
			$sections[] = [ 'start' => anchor_blocks_audio_seconds( $m[1] ), 'name' => $m[2] ];
		}
	}
	usort( $sections, function ( $a, $b ) { return $a['start'] <=> $b['start']; } );
	return $sections;
}

function anchor_blocks_render_audio( $attrs ) {
	$src      = esc_url( $attrs['src'] ?? '' );
	$title    = trim( (string) ( $attrs['title'] ?? '' ) );
	$meta     = trim( (string) ( $attrs['meta'] ?? '' ) );
	$link     = esc_url( $attrs['link'] ?? '' );
	$caption  = wp_kses_post( $attrs['caption'] ?? '' );
	$duration = max( 0, (float) ( $attrs['duration'] ?? 0 ) );
	if ( ! $src ) {
		return '';
	}

	$caption_html = $caption ? sprintf( '<figcaption class="ab-audio-caption">%s</figcaption>', $caption ) : '';
	$label        = '' !== $title ? $title : wp_basename( wp_parse_url( $src, PHP_URL_PATH ) );
	$title_html   = esc_html( $label );
	if ( $link ) {
		$title_html = sprintf( '<a href="%s">%s</a>', $link, $title_html );
	}

	// Feeds get the plain element: readers either play <audio> or show the link.
	if ( is_feed() ) {
		return sprintf(
			'<figure class="wp-block-anchor-audio"><p><strong>%s</strong></p><audio controls preload="none" src="%s"></audio>%s</figure>',
			$title_html, $src, $caption_html
		);
	}

	$peaks = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $attrs['peaks'] ?? '' ) ) ), 'is_numeric' ) );
	$bars  = '';
	foreach ( $peaks as $peak ) {
		$bars .= sprintf( '<i style="height:%d%%"></i>', max( 6, min( 100, (int) $peak ) ) );
	}

	// Sections need a length to sit against; without one they're left out.
	$sections_html = '';
	$sections      = $duration ? anchor_blocks_audio_sections( $attrs['sections'] ?? '' ) : [];
	foreach ( $sections as $i => $section ) {
		$start = min( $section['start'], $duration );
		$end   = isset( $sections[ $i + 1 ] ) ? min( $sections[ $i + 1 ]['start'], $duration ) : $duration;
		$sections_html .= sprintf(
			'<li style="left:%1$.3F%%;width:%2$.3F%%"><button type="button" data-t="%3$.2F" title="%4$s, %5$s" aria-label="Jump to %4$s, %5$s">%4$s</button></li>',
			$start / $duration * 100,
			max( 0, $end - $start ) / $duration * 100,
			$start,
			esc_attr( $section['name'] ),
			anchor_blocks_audio_time( $start )
		);
	}
	if ( $sections_html ) {
		$sections_html = '<ol class="ab-audio-sections">' . $sections_html . '</ol>';
	}

	$total = $duration ? anchor_blocks_audio_time( $duration ) : '0:00';

	return sprintf(
		'<figure class="wp-block-anchor-audio" data-title="%1$s" data-href="%2$s" data-duration="%3$.2F">'
			. '<div class="ab-audio-card">'
				. '<div class="ab-audio-top">'
					. '<button type="button" class="ab-audio-play" aria-label="Play %1$s" disabled>'
						. '<svg class="ab-audio-i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>'
						. '<svg class="ab-audio-i-pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>'
					. '</button>'
					. '<div class="ab-audio-head"><div class="ab-audio-title">%4$s</div>%5$s<div class="ab-audio-now" aria-hidden="true"></div></div>'
					. '<div class="ab-audio-time"><span class="ab-audio-elapsed">0:00</span> / <span class="ab-audio-total">%6$s</span></div>'
				. '</div>'
				. '<div class="ab-audio-wave%7$s" role="slider" tabindex="0" aria-label="Seek %1$s" aria-valuemin="0" aria-valuemax="%8$d" aria-valuenow="0" aria-valuetext="0:00 of %6$s">'
					. '<div class="ab-audio-bars" aria-hidden="true">%9$s</div><span class="ab-audio-line" aria-hidden="true"></span><span class="ab-audio-tip" aria-hidden="true"></span>'
				. '</div>'
				. '%10$s'
			. '</div>'
			. '<audio preload="none" src="%11$s"></audio>'
			. '<noscript><audio controls preload="none" src="%11$s"></audio></noscript>'
			. '%12$s'
		. '</figure>',
		esc_attr( $label ),
		esc_attr( $link ? $link : $src ),
		$duration,
		$title_html,
		'' !== $meta ? '<div class="ab-audio-meta">' . esc_html( $meta ) . '</div>' : '',
		$total,
		$bars ? '' : ' is-flat',
		(int) round( $duration ),
		$bars,
		$sections_html,
		$src,
		$caption_html
	);
}

/**
 * Email: replace the player with a card that links to the song page (or the file).
 */
function anchor_blocks_email_audio( $m ) {
	$attrs = $m[1];
	$title = preg_match( '/data-title="([^"]*)"/', $attrs, $t ) ? $t[1] : 'Listen';
	$href  = preg_match( '/data-href="([^"]*)"/', $attrs, $h ) ? $h[1] : '';
	$meta  = preg_match( '#<div class="ab-audio-meta">([\s\S]*?)</div>#', $m[2], $mm ) ? $mm[1] : '';
	$cap   = preg_match( '#<figcaption class="ab-audio-caption">([\s\S]*?)</figcaption>#', $m[2], $c ) ? $c[1] : '';
	$total = preg_match( '#<span class="ab-audio-total">([^<]*)</span>#', $m[2], $d ) ? $d[1] : '';
	$sub   = trim( implode( ' · ', array_filter( [ $meta, '0:00' !== $total ? $total : '' ] ) ) );

	return '<div class="wp-block-anchor-audio" style="margin:1.5rem 0">'
		. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:separate;background:#fff;border:1px solid #e4e9f0;border-radius:12px"><tr>'
		. '<td width="56" style="padding:14px 0 14px 16px;vertical-align:middle"><a href="' . $href . '" style="display:block;width:40px;height:40px;border-radius:20px;background:#123e8c;color:#fff;text-align:center;line-height:40px;font-size:16px;text-decoration:none">&#9654;</a></td>'
		. '<td style="padding:14px 16px 14px 12px;vertical-align:middle;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif">'
		. '<a href="' . $href . '" style="font-size:15px;font-weight:700;color:#15181d;text-decoration:none">' . $title . '</a>'
		. ( $sub ? '<div style="font-size:13px;color:#6b7f94;margin-top:2px">' . $sub . '</div>' : '' )
		. '<a href="' . $href . '" style="display:inline-block;margin-top:6px;font-size:13px;color:#2b8fc7">Listen on the web</a>'
		. '</td></tr></table>'
		. ( $cap ? '<p style="margin-top:0.6rem;font-size:0.85rem;line-height:1.5;color:#6b7f94;text-align:center">' . $cap . '</p>' : '' )
		. '</div>';
}
