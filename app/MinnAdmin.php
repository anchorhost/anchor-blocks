<?php
/**
 * Minn Admin integration — block-inspector form descriptors.
 *
 * Minn Admin (github.com/austinginder/minn-admin) renders complex blocks as
 * read-only islands in its editor and generates a config form for each from
 * the registered attribute schema. A schema can't express intent — that
 * `role` is a two-value enum, that `content` wants a textarea, or what a
 * friendly label is — so Minn exposes the `minn_admin_block_forms` filter
 * and this file registers Anchor Blocks' refinements. `wrapperText` patterns
 * (three capture groups: prefix / text / suffix) surface editable text living
 * in an InnerBlocks wrapper's saved HTML, like the conversation header.
 *
 * Harmless when Minn Admin isn't installed: the filter simply never runs.
 *
 * @package anchor-blocks
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'minn_admin_block_forms', function ( $forms ) {
	$colors = [
		[ 'blue', 'Blue' ],
		[ 'green', 'Green' ],
		[ 'orange', 'Orange' ],
		[ 'red', 'Red' ],
		[ 'purple', 'Purple' ],
	];

	$forms['anchor/conversation'] = [
		'wrapperText' => [
			[
				'label'   => 'Header',
				'pattern' => '(<div class="ab-conv-header">)([^<]*)(</div>)',
			],
		],
		'insert'      => [
			'label'    => 'Conversation',
			'template' => "<!-- wp:anchor/conversation -->\n<div class=\"wp-block-anchor-conversation\"><div class=\"ab-conv-header\">Claude Code Session</div><!-- wp:anchor/conversation-message {\"content\":\"First message.\"} /-->\n\n<!-- wp:anchor/conversation-message {\"role\":\"assistant\",\"label\":\"Claude\",\"content\":\"First reply.\"} /--></div>\n<!-- /wp:anchor/conversation -->",
		],
	];
	$forms['anchor/conversation-message'] = [
		'order'      => [ 'role', 'label', 'content' ],
		'attributes' => [
			'role'    => [
				'label'   => 'Role',
				'control' => 'select',
				'options' => [ [ 'user', 'User' ], [ 'assistant', 'Assistant' ] ],
			],
			'label'   => [ 'label' => 'Speaker' ],
			'content' => [ 'label' => 'Message', 'control' => 'textarea' ],
		],
	];
	$forms['anchor/stat-card'] = [
		'order'      => [ 'value', 'label', 'color' ],
		'attributes' => [
			'value' => [ 'label' => 'Value' ],
			'label' => [ 'label' => 'Label' ],
			'color' => [ 'label' => 'Color', 'control' => 'select', 'options' => $colors ],
		],
	];
	$forms['anchor/timeline'] = [
		'insert' => [
			'label'    => 'Timeline',
			'template' => "<!-- wp:anchor/timeline -->\n<div class=\"wp-block-anchor-timeline\"><!-- wp:anchor/timeline-item {\"date\":\"Today\",\"content\":\"First event.\"} /--></div>\n<!-- /wp:anchor/timeline -->",
		],
	];
	$forms['anchor/timeline-item'] = [
		'order'      => [ 'date', 'content', 'color' ],
		'attributes' => [
			'date'    => [ 'label' => 'Date' ],
			'content' => [ 'label' => 'Content', 'control' => 'textarea' ],
			'color'   => [ 'label' => 'Color', 'control' => 'select', 'options' => array_slice( $colors, 0, 4 ) ],
		],
	];
	$forms['anchor/callout'] = [
		'order'      => [ 'title', 'content', 'style' ],
		'attributes' => [
			'title'   => [ 'label' => 'Title' ],
			'content' => [ 'label' => 'Content', 'control' => 'textarea' ],
			'style'   => [
				'label'   => 'Style',
				'control' => 'select',
				'options' => [ [ 'blue', 'Blue' ], [ 'green', 'Green' ], [ 'red', 'Red' ], [ 'yellow', 'Yellow' ] ],
			],
		],
		'insert'     => [
			'label'    => 'Callout',
			'template' => '<!-- wp:anchor/callout {"title":"Callout title","content":"Callout content."} /-->',
		],
	];
	$forms['anchor/stats-dashboard'] = [
		'insert' => [
			'label'    => 'Stats Dashboard',
			'template' => "<!-- wp:anchor/stats-dashboard {\"className\":\"wp-block-anchor-stats-dashboard\"} -->\n<!-- wp:anchor/stat-card {\"value\":\"0\",\"label\":\"Label\"} /-->\n\n<!-- wp:anchor/stat-card {\"value\":\"0\",\"label\":\"Label\",\"color\":\"green\"} /-->\n<!-- /wp:anchor/stats-dashboard -->",
		],
	];
	$forms['anchor/bar-chart'] = [
		'attributes' => [ 'title' => [ 'label' => 'Title' ] ],
		'insert'     => [
			'label'    => 'Bar Chart',
			'template' => "<!-- wp:anchor/bar-chart {\"title\":\"Chart title\",\"className\":\"wp-block-anchor-bar-chart\"} -->\n<!-- wp:anchor/bar-row {\"label\":\"Category\",\"value\":\"0\",\"percent\":50} /-->\n<!-- /wp:anchor/bar-chart -->",
		],
	];
	$forms['anchor/bar-row'] = [
		'order'      => [ 'label', 'value', 'percent', 'color' ],
		'attributes' => [
			'label'   => [ 'label' => 'Label' ],
			'value'   => [ 'label' => 'Value' ],
			'percent' => [ 'label' => 'Bar width (%)' ],
			'color'   => [ 'label' => 'Color', 'control' => 'select', 'options' => $colors ],
		],
	];
	$forms['anchor/ioc-list'] = [
		'attributes' => [ 'title' => [ 'label' => 'Title' ] ],
	];
	$forms['anchor/ioc-row'] = [
		'order'      => [ 'label', 'value', 'note', 'color' ],
		'attributes' => [
			'label' => [ 'label' => 'Type' ],
			'value' => [ 'label' => 'Indicator', 'control' => 'textarea' ],
			'note'  => [ 'label' => 'Note' ],
			'color' => [ 'label' => 'Color', 'control' => 'select', 'options' => $colors ],
		],
	];
	$forms['anchor/vector-cards'] = [
		'attributes' => [ 'title' => [ 'label' => 'Title' ] ],
	];
	$forms['anchor/vector-card'] = [
		'order'      => [ 'label', 'title', 'content', 'detect', 'color' ],
		'attributes' => [
			'label'   => [ 'label' => 'Badge' ],
			'title'   => [ 'label' => 'Title' ],
			'content' => [ 'label' => 'Content', 'control' => 'textarea' ],
			'detect'  => [ 'label' => 'Detection note', 'control' => 'textarea' ],
			'color'   => [ 'label' => 'Color', 'control' => 'select', 'options' => $colors ],
		],
	];
	$forms['anchor/term-list'] = [
		'attributes' => [ 'title' => [ 'label' => 'Title' ] ],
	];
	$forms['anchor/data-table'] = [
		'attributes' => [
			'title'     => [ 'label' => 'Title' ],
			'highlight' => [ 'label' => 'Highlight row (index)' ],
			// columns / align / rows are arrays — Minn's generic form skips them.
		],
	];

	return $forms;
} );
