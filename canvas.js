/* Anchor Blocks — canvas expand (anchor/canvas with "Click to expand").
 *
 * Adds an expand button to the canvas and opens it in a modal at the
 * largest size that fits the window. The modal gets a fresh shadow root
 * from the canvas's untouched markup and runs its JS again, so animations
 * start from the beginning. Without JS the canvas stays as it is.
 */
( function () {
	'use strict';

	var ICON_OPEN = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12 3h5v5M8 17H3v-5M17 3l-5.5 5.5M3 17l5.5-5.5"/></svg>';
	var ICON_CLOSE = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>';
	var INTERACTIVE = /^(A|BUTTON|INPUT|SELECT|TEXTAREA|LABEL|SUMMARY|AUDIO|VIDEO)$/;

	var dialog, frame, opener, current, keyboard;

	function build() {
		dialog = document.createElement( 'dialog' );
		dialog.className = 'ab-canvas-dialog';
		// Focusable, so open() can focus it instead of its first button.
		dialog.tabIndex = -1;
		dialog.innerHTML = '<div class="ab-canvas-dialog-frame"></div>'
			+ '<button type="button" class="ab-canvas-dialog-close" aria-label="Close">' + ICON_CLOSE + '</button>';
		frame = dialog.firstChild;

		// A click on the backdrop lands on the dialog itself.
		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === dialog || e.target.closest( '.ab-canvas-dialog-close' ) ) {
				dialog.close();
			}
		} );
		dialog.addEventListener( 'close', function () {
			frame.replaceChildren();
			current = null;
			document.documentElement.classList.remove( 'ab-canvas-open' );
			// Hand focus back to the button only for keyboard users. After a
			// click or tap, a ring on it would just look stuck.
			if ( opener && keyboard ) {
				opener.focus( { preventScroll: true } );
			} else if ( opener && document.activeElement === opener ) {
				opener.blur();
			}
		} );
		window.addEventListener( 'resize', function () {
			if ( current ) {
				fit( current );
			}
		} );
		document.body.appendChild( dialog );
	}

	// The widest size whose height fits the window. A container query can
	// switch the layout (and its aspect) as the width changes, so try a few
	// widths and keep the largest that fits, else the one that overflows least.
	function fit( host ) {
		var maxW = Math.min( 1440, document.documentElement.clientWidth - 32 );
		var maxH = window.innerHeight - 112;
		var w = maxW, best = 0, least = Infinity, leastW = maxW;
		for ( var i = 0; i < 6 && w >= 240; i++ ) {
			frame.style.width = w + 'px';
			var h = host.getBoundingClientRect().height;
			if ( h <= maxH + 1 ) {
				best = Math.max( best, w );
				break;
			}
			if ( h - maxH < least ) {
				least = h - maxH;
				leastW = w;
			}
			w = Math.floor( w * maxH / h );
		}
		frame.style.width = ( best || leastW ) + 'px';
	}

	function open( fig, viaKeyboard ) {
		var src = fig.querySelector( '.ab-canvas-host' );
		if ( ! src || ! src.shadowRoot ) {
			return;
		}
		if ( ! dialog ) {
			build();
		}
		opener = fig.querySelector( '.ab-canvas-expand' );
		keyboard = viaKeyboard;

		var host = document.createElement( 'div' );
		host.className = 'ab-canvas-host';
		var root = host.attachShadow( { mode: 'open' } );
		root.innerHTML = src.abCanvasMarkup || src.shadowRoot.innerHTML;
		frame.className = 'ab-canvas-dialog-frame' + ( fig.classList.contains( 'is-framed' ) ? ' is-framed' : '' );
		frame.appendChild( host );

		var caption = fig.querySelector( '.ab-canvas-caption' );
		dialog.setAttribute( 'aria-label', caption ? caption.textContent : 'Canvas' );
		document.documentElement.classList.add( 'ab-canvas-open' );
		dialog.showModal();
		dialog.focus( { preventScroll: true } );
		current = host;
		fit( host );

		if ( typeof src.abCanvasRun === 'function' ) {
			src.abCanvasRun.call( host, root, host );
		}
	}

	function setup( fig ) {
		var stage = fig.querySelector( '.ab-canvas-stage' );
		var host = fig.querySelector( '.ab-canvas-host' );
		if ( ! stage || ! host || ! host.shadowRoot || fig.abCanvasExpand ) {
			return;
		}
		fig.abCanvasExpand = true;

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'ab-canvas-expand';
		button.setAttribute( 'aria-label', 'Open larger' );
		button.innerHTML = ICON_OPEN;
		stage.appendChild( button );
		fig.classList.add( 'is-expandable' );

		// The whole stage opens the modal, except the canvas's own controls.
		stage.addEventListener( 'click', function ( e ) {
			var path = e.composedPath();
			for ( var i = 0; i < path.length && path[ i ] !== stage; i++ ) {
				var node = path[ i ];
				if ( node !== button && node.nodeType === 1 && ( INTERACTIVE.test( node.nodeName.toUpperCase() ) || node.hasAttribute( 'tabindex' ) ) ) {
					return;
				}
			}
			// A button pressed with Enter or Space clicks with detail 0.
			open( fig, e.detail === 0 );
		} );
	}

	if ( typeof HTMLDialogElement !== 'function' ) {
		return;
	}
	Array.prototype.forEach.call( document.querySelectorAll( '.wp-block-anchor-canvas[data-expand]' ), setup );
} )();
