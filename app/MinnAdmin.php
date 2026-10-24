<?php
/**
 * Minn Admin integration — block-inspector refinements + insert templates.
 *
 * Minn Admin (github.com/austinginder/minn-admin) renders complex blocks as
 * read-only islands and generates a config form for each from the registered
 * attribute schema. Since the schema itself carries titles, `parent` gating
 * and `enum` choice lists (anchor-blocks.php), Minn derives labels, selects
 * and top-level insertability with no help — this file only adds what a
 * schema cannot express:
 *
 * - `insert` templates for the static-save InnerBlocks wrappers (Conversation,
 *   Timeline — bare self-closing comments are not valid saved markup for
 *   them, so without a template they are uninsertable in Minn) and seeded
 *   starters for the server-rendered wrappers (Stats Dashboard, Bar Chart)
 *   and Callout, which would otherwise insert empty.
 * - Semantic field labels where the attribute name reads wrong when
 *   humanized (`label` → "Speaker" / "Type" / "Badge", `percent` →
 *   "Bar width (%)"), and textareas for two long-text attrs not named
 *   `content`.
 * - Audio: labels and textareas for its sections and peaks.
 * - Canvas: textareas for its code attributes. Minn previews islands from
 *   server HTML inserted without the parser, so the declarative shadow root
 *   never attaches there and the canvas shows its fallback image instead.
 *
 * Harmless when Minn Admin isn't installed: the filter simply never runs.
 *
 * @package anchor-blocks
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'minn_admin_block_forms', function ( $forms ) {
	$forms['anchor/conversation'] = [
		'insert' => [
			'label'    => 'Conversation',
			'template' => "<!-- wp:anchor/conversation -->\n<div class=\"wp-block-anchor-conversation\"><div class=\"ab-conv-header\">Claude Code Session</div><!-- wp:anchor/conversation-message {\"content\":\"First message.\"} /-->\n\n<!-- wp:anchor/conversation-message {\"role\":\"assistant\",\"label\":\"Claude\",\"content\":\"First reply.\"} /--></div>\n<!-- /wp:anchor/conversation -->",
		],
	];
	$forms['anchor/timeline'] = [
		'insert' => [
			'label'    => 'Timeline',
			'template' => "<!-- wp:anchor/timeline -->\n<div class=\"wp-block-anchor-timeline\"><!-- wp:anchor/timeline-item {\"date\":\"Today\",\"content\":\"First event.\"} /--></div>\n<!-- /wp:anchor/timeline -->",
		],
	];
	$forms['anchor/stats-dashboard'] = [
		'insert' => [
			'label'    => 'Stats Dashboard',
			'template' => "<!-- wp:anchor/stats-dashboard {\"className\":\"wp-block-anchor-stats-dashboard\"} -->\n<!-- wp:anchor/stat-card {\"value\":\"0\",\"label\":\"Label\"} /-->\n\n<!-- wp:anchor/stat-card {\"value\":\"0\",\"label\":\"Label\",\"color\":\"green\"} /-->\n<!-- /wp:anchor/stats-dashboard -->",
		],
	];
	$forms['anchor/bar-chart'] = [
		'insert' => [
			'label'    => 'Bar Chart',
			'template' => "<!-- wp:anchor/bar-chart {\"title\":\"Chart title\",\"className\":\"wp-block-anchor-bar-chart\"} -->\n<!-- wp:anchor/bar-row {\"label\":\"Category\",\"value\":\"0\",\"percent\":50} /-->\n<!-- /wp:anchor/bar-chart -->",
		],
	];
	$forms['anchor/callout'] = [
		'insert'     => [
			'label'    => 'Callout',
			'template' => '<!-- wp:anchor/callout {"title":"Callout title","content":"Callout content."} /-->',
		],
		'attributes' => [
			// The schema enum would render Blue/Red/Yellow/Green — these carry intent.
			'style' => [
				'label'   => 'Style',
				'control' => 'select',
				'options' => [
					[ 'blue', 'Blue (Info)' ],
					[ 'red', 'Red (Danger)' ],
					[ 'yellow', 'Yellow (Warning)' ],
					[ 'green', 'Green (Success)' ],
				],
			],
		],
	];
	$forms['anchor/conversation-message'] = [
		'attributes' => [
			'label'   => [ 'label' => 'Speaker' ],
			'content' => [ 'label' => 'Message' ],
		],
	];
	$forms['anchor/bar-row'] = [
		'attributes' => [
			'percent' => [ 'label' => 'Bar width (%)' ],
		],
	];
	$forms['anchor/ioc-row'] = [
		'attributes' => [
			'label' => [ 'label' => 'Type' ],
			'value' => [ 'label' => 'Indicator', 'control' => 'textarea' ],
		],
	];
	$forms['anchor/vector-card'] = [
		'attributes' => [
			'label'  => [ 'label' => 'Badge' ],
			'detect' => [ 'label' => 'Detection note', 'control' => 'textarea' ],
		],
	];
	$forms['anchor/data-table'] = [
		'attributes' => [
			'highlight' => [ 'label' => 'Highlight row (index)' ],
			// columns / align / rows are arrays — Minn's generic form skips them.
		],
	];

	$forms['anchor/canvas'] = [
		'insert'     => [
			'label'    => 'Canvas',
			'template' => '<!-- wp:anchor/canvas {"caption":"Caption"} /-->',
		],
		'attributes' => [
			'html'        => [ 'label' => 'HTML / SVG', 'control' => 'textarea' ],
			'css'         => [ 'label' => 'CSS (scoped; :host is the wrapper)', 'control' => 'textarea' ],
			'js'          => [ 'label' => 'JS (runs on the page with root and host)', 'control' => 'textarea' ],
			'fallbackId'  => [ 'label' => 'Fallback image (attachment ID)' ],
			'fallbackAlt' => [ 'label' => 'Fallback alt text' ],
		],
	];

	$forms['anchor/audio'] = [
		'insert'     => [
			'label'    => 'Audio',
			'template' => '<!-- wp:anchor/audio {"title":"Title"} /-->',
		],
		'attributes' => [
			'src'      => [ 'label' => 'Audio URL' ],
			'meta'     => [ 'label' => 'Subtitle' ],
			'link'     => [ 'label' => 'Title link (and email link)' ],
			'duration' => [ 'label' => 'Length (seconds)' ],
			'sections' => [ 'label' => 'Sections (one per line: "1:17 Drop")', 'control' => 'textarea' ],
			'peaks'    => [ 'label' => 'Waveform peaks (wp anchor-blocks waveform <url>)', 'control' => 'textarea' ],
		],
	];

	return $forms;
} );
