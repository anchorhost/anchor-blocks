( function( blocks, element, blockEditor, components ) {
	const el = element.createElement;
	const { RichText, InnerBlocks, InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, SelectControl, TextControl } = components;

	/* =====================================================================
	   CONVERSATION — Parent block
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/conversation', {
		apiVersion: 3,
		title: 'Conversation',
		icon: 'format-chat',
		category: 'text',
		description: 'A Claude Code conversation session with user and assistant messages.',
		attributes: {
			header: { type: 'string', default: 'Claude Code Session' }
		},
		edit: function( props ) {
			const blockProps = useBlockProps( { className: 'wp-block-anchor-conversation' } );
			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Settings' },
						el( TextControl, {
							label: 'Header text',
							value: props.attributes.header,
							onChange: function( val ) { props.setAttributes( { header: val } ); }
						} )
					)
				),
				el( 'div', { className: 'ab-conv-header' }, props.attributes.header ),
				el( InnerBlocks, {
					allowedBlocks: [ 'anchor/conversation-message' ],
					template: [
						[ 'anchor/conversation-message', { role: 'user', label: 'Austin' } ],
						[ 'anchor/conversation-message', { role: 'assistant', label: 'Claude' } ]
					]
				} )
			);
		},
		save: function( props ) {
			const blockProps = useBlockProps.save();
			return el( 'div', blockProps,
				el( 'div', { className: 'ab-conv-header' }, props.attributes.header ),
				el( InnerBlocks.Content )
			);
		}
	} );

	/* =====================================================================
	   CONVERSATION MESSAGE — Child block (server-rendered)
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/conversation-message', {
		apiVersion: 3,
		title: 'Conversation Message',
		icon: 'admin-comments',
		category: 'text',
		parent: [ 'anchor/conversation' ],
		attributes: {
			role:    { type: 'string', default: 'user' },
			label:   { type: 'string', default: 'Austin' },
			content: { type: 'string', default: '' }
		},
		edit: function( props ) {
			const { role, label, content } = props.attributes;
			const className = 'wp-block-anchor-conversation-message is-role-' + role;
			const blockProps = useBlockProps( { className: className } );

			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Message Settings' },
						el( SelectControl, {
							label: 'Role',
							value: role,
							options: [
								{ label: 'User', value: 'user' },
								{ label: 'Assistant', value: 'assistant' }
							],
							onChange: function( val ) {
								var defaultLabel = val === 'user' ? 'Austin' : 'Claude';
								props.setAttributes( { role: val, label: defaultLabel } );
							}
						} ),
						el( TextControl, {
							label: 'Display name',
							value: label,
							onChange: function( val ) { props.setAttributes( { label: val } ); }
						} )
					)
				),
				el( 'div', { className: 'ab-msg-label ' + role }, label ),
				el( RichText, {
					tagName: 'div',
					className: 'ab-msg-content',
					value: content,
					onChange: function( val ) { props.setAttributes( { content: val } ); },
					placeholder: 'Type message...'
				} )
			);
		},
		save: function() { return null; }
	} );

	/* =====================================================================
	   TIMELINE — Parent block
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/timeline', {
		apiVersion: 3,
		title: 'Timeline',
		icon: 'backup',
		category: 'text',
		description: 'A chronological timeline with colored event markers.',
		edit: function( props ) {
			const blockProps = useBlockProps( { className: 'wp-block-anchor-timeline' } );
			return el( 'div', blockProps,
				el( InnerBlocks, {
					allowedBlocks: [ 'anchor/timeline-item' ],
					template: [
						[ 'anchor/timeline-item', { color: 'blue' } ]
					]
				} )
			);
		},
		save: function() {
			const blockProps = useBlockProps.save();
			return el( 'div', blockProps,
				el( InnerBlocks.Content )
			);
		}
	} );

	/* =====================================================================
	   TIMELINE ITEM — Child block (server-rendered)
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/timeline-item', {
		apiVersion: 3,
		title: 'Timeline Item',
		icon: 'marker',
		category: 'text',
		parent: [ 'anchor/timeline' ],
		attributes: {
			color:   { type: 'string', default: 'blue' },
			date:    { type: 'string', default: '' },
			content: { type: 'string', default: '' }
		},
		edit: function( props ) {
			const { color, date, content } = props.attributes;
			const className = 'wp-block-anchor-timeline-item is-color-' + color;
			const blockProps = useBlockProps( { className: className } );

			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Item Settings' },
						el( SelectControl, {
							label: 'Dot color',
							value: color,
							options: [
								{ label: 'Blue', value: 'blue' },
								{ label: 'Red', value: 'red' },
								{ label: 'Orange', value: 'orange' },
								{ label: 'Green', value: 'green' }
							],
							onChange: function( val ) { props.setAttributes( { color: val } ); }
						} )
					)
				),
				el( RichText, {
					tagName: 'div',
					className: 'ab-tl-date',
					value: date,
					onChange: function( val ) { props.setAttributes( { date: val } ); },
					placeholder: 'Date...'
				} ),
				el( RichText, {
					tagName: 'div',
					className: 'ab-tl-text',
					value: content,
					onChange: function( val ) { props.setAttributes( { content: val } ); },
					placeholder: 'Event description...'
				} )
			);
		},
		save: function() { return null; }
	} );

	/* =====================================================================
	   CALLOUT — Standalone block (server-rendered)
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/callout', {
		apiVersion: 3,
		title: 'Callout',
		icon: 'info-outline',
		category: 'text',
		description: 'A highlighted callout box with a title and content.',
		attributes: {
			style:   { type: 'string', default: 'blue' },
			title:   { type: 'string', default: '' },
			content: { type: 'string', default: '' }
		},
		edit: function( props ) {
			const { style, title, content } = props.attributes;
			const className = 'wp-block-anchor-callout is-style-' + style;
			const blockProps = useBlockProps( { className: className } );

			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Callout Settings' },
						el( SelectControl, {
							label: 'Style',
							value: style,
							options: [
								{ label: 'Blue (Info)', value: 'blue' },
								{ label: 'Red (Danger)', value: 'red' },
								{ label: 'Yellow (Warning)', value: 'yellow' },
								{ label: 'Green (Success)', value: 'green' }
							],
							onChange: function( val ) { props.setAttributes( { style: val } ); }
						} )
					)
				),
				el( RichText, {
					tagName: 'div',
					className: 'ab-callout-title',
					value: title,
					onChange: function( val ) { props.setAttributes( { title: val } ); },
					placeholder: 'Callout title...'
				} ),
				el( RichText, {
					tagName: 'div',
					className: 'ab-callout-content',
					value: content,
					onChange: function( val ) { props.setAttributes( { content: val } ); },
					placeholder: 'Callout content...'
				} )
			);
		},
		save: function() { return null; }
	} );

	/* =====================================================================
	   STATS DASHBOARD — Parent block
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/stats-dashboard', {
		apiVersion: 3,
		title: 'Stats Dashboard',
		icon: 'dashboard',
		category: 'text',
		description: 'A row of stat cards with large numbers and labels.',
		edit: function( props ) {
			const blockProps = useBlockProps( { className: 'wp-block-anchor-stats-dashboard' } );
			return el( 'div', blockProps,
				el( InnerBlocks, {
					allowedBlocks: [ 'anchor/stat-card' ],
					orientation: 'horizontal',
					template: [
						[ 'anchor/stat-card', { value: '0', label: 'Emails Closed', color: 'blue' } ],
						[ 'anchor/stat-card', { value: '0', label: 'Sites Fixed', color: 'green' } ],
						[ 'anchor/stat-card', { value: '0', label: 'Alerts Reviewed', color: 'red' } ],
						[ 'anchor/stat-card', { value: '0', label: 'Emails Drafted', color: 'purple' } ]
					]
				} )
			);
		},
		save: function() {
			return el( InnerBlocks.Content );
		}
	} );

	/* =====================================================================
	   STAT CARD — Child block (server-rendered)
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/stat-card', {
		apiVersion: 3,
		title: 'Stat Card',
		icon: 'chart-bar',
		category: 'text',
		parent: [ 'anchor/stats-dashboard' ],
		attributes: {
			value: { type: 'string', default: '0' },
			label: { type: 'string', default: 'Label' },
			color: { type: 'string', default: 'blue' }
		},
		edit: function( props ) {
			const { value, label, color } = props.attributes;
			const className = 'wp-block-anchor-stat-card is-color-' + color;
			const blockProps = useBlockProps( { className: className } );

			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Card Settings' },
						el( SelectControl, {
							label: 'Color',
							value: color,
							options: [
								{ label: 'Blue', value: 'blue' },
								{ label: 'Green', value: 'green' },
								{ label: 'Red', value: 'red' },
								{ label: 'Purple', value: 'purple' },
								{ label: 'Orange', value: 'orange' }
							],
							onChange: function( val ) { props.setAttributes( { color: val } ); }
						} )
					)
				),
				el( RichText, {
					tagName: 'div',
					className: 'ab-stat-value',
					value: value,
					onChange: function( val ) { props.setAttributes( { value: val } ); },
					placeholder: '0'
				} ),
				el( RichText, {
					tagName: 'div',
					className: 'ab-stat-label',
					value: label,
					onChange: function( val ) { props.setAttributes( { label: val } ); },
					placeholder: 'Label'
				} )
			);
		},
		save: function() { return null; }
	} );

	/* =====================================================================
	   BAR CHART — Parent block
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/bar-chart', {
		apiVersion: 3,
		title: 'Bar Chart',
		icon: 'chart-bar',
		category: 'text',
		description: 'A horizontal bar chart with labeled rows.',
		attributes: {
			title: { type: 'string', default: '' }
		},
		edit: function( props ) {
			const blockProps = useBlockProps( { className: 'wp-block-anchor-bar-chart' } );
			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Chart Settings' },
						el( TextControl, {
							label: 'Chart title',
							value: props.attributes.title,
							onChange: function( val ) { props.setAttributes( { title: val } ); }
						} )
					)
				),
				props.attributes.title ? el( 'div', { className: 'ab-chart-title' }, props.attributes.title ) : null,
				el( InnerBlocks, {
					allowedBlocks: [ 'anchor/bar-row' ],
					template: [
						[ 'anchor/bar-row', { label: 'Category A', value: '50', percent: 50, color: 'blue' } ],
						[ 'anchor/bar-row', { label: 'Category B', value: '30', percent: 30, color: 'green' } ]
					]
				} )
			);
		},
		save: function() {
			return el( InnerBlocks.Content );
		}
	} );

	/* =====================================================================
	   BAR ROW — Child block (server-rendered)
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/bar-row', {
		apiVersion: 3,
		title: 'Bar Row',
		icon: 'minus',
		category: 'text',
		parent: [ 'anchor/bar-chart' ],
		attributes: {
			label:   { type: 'string', default: 'Category' },
			value:   { type: 'string', default: '0' },
			percent: { type: 'number', default: 50 },
			color:   { type: 'string', default: 'blue' }
		},
		edit: function( props ) {
			var RangeControl = components.RangeControl;
			const { label, value, percent, color } = props.attributes;
			const blockProps = useBlockProps( { className: 'wp-block-anchor-bar-row' } );

			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Bar Settings' },
						el( RangeControl, {
							label: 'Fill percentage',
							value: percent,
							min: 1,
							max: 100,
							onChange: function( val ) { props.setAttributes( { percent: val } ); }
						} ),
						el( SelectControl, {
							label: 'Color',
							value: color,
							options: [
								{ label: 'Blue', value: 'blue' },
								{ label: 'Green', value: 'green' },
								{ label: 'Red', value: 'red' },
								{ label: 'Purple', value: 'purple' },
								{ label: 'Orange', value: 'orange' },
								{ label: 'Teal', value: 'teal' },
								{ label: 'Gray', value: 'gray' }
							],
							onChange: function( val ) { props.setAttributes( { color: val } ); }
						} )
					)
				),
				el( 'div', { className: 'ab-bar-label' },
					el( RichText, {
						tagName: 'span',
						value: label,
						onChange: function( val ) { props.setAttributes( { label: val } ); },
						placeholder: 'Label'
					} )
				),
				el( 'div', { className: 'ab-bar-track' },
					el( 'div', {
						className: 'ab-bar-fill is-color-' + color,
						style: { width: percent + '%' }
					},
						el( RichText, {
							tagName: 'span',
							value: value,
							onChange: function( val ) { props.setAttributes( { value: val } ); },
							placeholder: '0'
						} )
					)
				)
			);
		},
		save: function() { return null; }
	} );

	/* =====================================================================
	   REPORT CARD — Standalone block with InnerBlocks (server-rendered)
	   ===================================================================== */

	blocks.registerBlockType( 'anchor/report-card', {
		apiVersion: 3,
		title: 'Report Card',
		icon: 'media-text',
		category: 'text',
		description: 'A card with tag badge, title, optional count, and rich body content.',
		attributes: {
			tag:      { type: 'string', default: '' },
			tagColor: { type: 'string', default: 'blue' },
			title:    { type: 'string', default: '' },
			count:    { type: 'string', default: '' }
		},
		edit: function( props ) {
			const { tag, tagColor, title, count } = props.attributes;
			const blockProps = useBlockProps( { className: 'wp-block-anchor-report-card' } );

			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( PanelBody, { title: 'Card Settings' },
						el( TextControl, {
							label: 'Tag label',
							value: tag,
							onChange: function( val ) { props.setAttributes( { tag: val } ); }
						} ),
						el( SelectControl, {
							label: 'Tag color',
							value: tagColor,
							options: [
								{ label: 'Blue', value: 'blue' },
								{ label: 'Green', value: 'green' },
								{ label: 'Red', value: 'red' },
								{ label: 'Purple', value: 'purple' },
								{ label: 'Orange', value: 'orange' }
							],
							onChange: function( val ) { props.setAttributes( { tagColor: val } ); }
						} ),
						el( TextControl, {
							label: 'Count badge',
							value: count,
							onChange: function( val ) { props.setAttributes( { count: val } ); }
						} )
					)
				),
				el( 'div', { className: 'ab-rc-header' },
					tag ? el( 'span', { className: 'ab-rc-tag is-color-' + tagColor }, tag ) : null,
					el( RichText, {
						tagName: 'div',
						className: 'ab-rc-title',
						value: title,
						onChange: function( val ) { props.setAttributes( { title: val } ); },
						placeholder: 'Card title...'
					} ),
					count ? el( 'span', { className: 'ab-rc-count' }, count ) : null
				),
				el( 'div', { className: 'ab-rc-body' },
					el( InnerBlocks, {
						template: [
							[ 'core/paragraph', { placeholder: 'Card content...' } ]
						]
					} )
				)
			);
		},
		save: function() { return el( InnerBlocks.Content ); }
	} );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
