<?php
/**
 * Plugin Name: Anchor Blocks
 * Description: Custom blocks for Anchor Hosting blog posts — Conversation, Timeline, Callout, Stats Dashboard, Bar Chart, Report Card, and Indicators of Compromise.
 * Version: 1.3.0
 * Author: Austin Ginder
 * Author URI: https://anchor.host
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ANCHOR_BLOCKS_VERSION', '1.3.0' );
define( 'ANCHOR_BLOCKS_URL', plugin_dir_url( __FILE__ ) );
define( 'ANCHOR_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );

require_once ANCHOR_BLOCKS_DIR . 'app/Updater.php';
new AnchorBlocks\Updater();

add_action( 'init', function() {
	wp_register_script(
		'anchor-blocks-editor',
		ANCHOR_BLOCKS_URL . 'editor.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
		ANCHOR_BLOCKS_VERSION,
		true
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

	// Parent blocks — save handled in JS via InnerBlocks.Content
	foreach ( [ 'anchor/conversation', 'anchor/timeline' ] as $block ) {
		register_block_type( $block, $block_args );
	}

	// Parent blocks with server-rendered children — need render_callback
	register_block_type( 'anchor/stats-dashboard', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_stats_dashboard',
	] ) );

	register_block_type( 'anchor/bar-chart', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_bar_chart',
		'attributes'      => [
			'title' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	register_block_type( 'anchor/ioc-list', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_ioc_list',
		'attributes'      => [
			'title' => [ 'type' => 'string', 'default' => 'Indicators of Compromise' ],
		],
	] ) );

	// Child/leaf blocks — server-side rendered to avoid validation errors
	register_block_type( 'anchor/conversation-message', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_conversation_message',
		'attributes'      => [
			'role'    => [ 'type' => 'string', 'default' => 'user' ],
			'label'   => [ 'type' => 'string', 'default' => 'Austin' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	register_block_type( 'anchor/timeline-item', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_timeline_item',
		'attributes'      => [
			'color'   => [ 'type' => 'string', 'default' => 'blue' ],
			'date'    => [ 'type' => 'string', 'default' => '' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	register_block_type( 'anchor/callout', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_callout',
		'attributes'      => [
			'style'   => [ 'type' => 'string', 'default' => 'blue' ],
			'title'   => [ 'type' => 'string', 'default' => '' ],
			'content' => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	// Stats Dashboard — child card (server-rendered)
	register_block_type( 'anchor/stat-card', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_stat_card',
		'attributes'      => [
			'value' => [ 'type' => 'string', 'default' => '0' ],
			'label' => [ 'type' => 'string', 'default' => 'Label' ],
			'color' => [ 'type' => 'string', 'default' => 'blue' ],
		],
	] ) );

	// Bar Chart — child row (server-rendered)
	register_block_type( 'anchor/bar-row', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_bar_row',
		'attributes'      => [
			'label'   => [ 'type' => 'string', 'default' => 'Category' ],
			'value'   => [ 'type' => 'string', 'default' => '0' ],
			'percent' => [ 'type' => 'number', 'default' => 50 ],
			'color'   => [ 'type' => 'string', 'default' => 'blue' ],
		],
	] ) );

	// IOC List — child row (server-rendered)
	register_block_type( 'anchor/ioc-row', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_ioc_row',
		'attributes'      => [
			'label' => [ 'type' => 'string', 'default' => 'Domain' ],
			'color' => [ 'type' => 'string', 'default' => 'red' ],
			'value' => [ 'type' => 'string', 'default' => '' ],
			'note'  => [ 'type' => 'string', 'default' => '' ],
		],
	] ) );

	// Report Card — server-rendered with InnerBlocks
	register_block_type( 'anchor/report-card', array_merge( $block_args, [
		'render_callback' => 'anchor_blocks_render_report_card',
		'attributes'      => [
			'tag'      => [ 'type' => 'string', 'default' => '' ],
			'tagColor' => [ 'type' => 'string', 'default' => 'blue' ],
			'title'    => [ 'type' => 'string', 'default' => '' ],
			'count'    => [ 'type' => 'string', 'default' => '' ],
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
