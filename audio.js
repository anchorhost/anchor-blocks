/* Anchor Blocks — audio player (anchor/audio).
 *
 * The server renders the whole card; this wires it to the <audio> element.
 * Without JS the card stays put with a disabled button and the <noscript>
 * native player shows below it.
 */
( function () {
	'use strict';

	var players = [];

	function fmt( s ) {
		s = Math.max( 0, Math.floor( s || 0 ) );
		return Math.floor( s / 60 ) + ':' + ( '0' + ( s % 60 ) ).slice( -2 );
	}

	function setup( fig ) {
		var audio = fig.querySelector( 'audio' );
		var play = fig.querySelector( '.ab-audio-play' );
		var wave = fig.querySelector( '.ab-audio-wave' );
		if ( ! audio || ! play || ! wave || fig.abAudio ) {
			return;
		}
		fig.abAudio = true;

		var bars = Array.prototype.slice.call( fig.querySelectorAll( '.ab-audio-bars i' ) );
		var elapsed = fig.querySelector( '.ab-audio-elapsed' );
		var total = fig.querySelector( '.ab-audio-total' );
		var now = fig.querySelector( '.ab-audio-now' );
		var tip = fig.querySelector( '.ab-audio-tip' );
		var marks = Array.prototype.slice.call( fig.querySelectorAll( '.ab-audio-sections button' ) );
		var title = fig.getAttribute( 'data-title' ) || '';
		var duration = parseFloat( fig.getAttribute( 'data-duration' ) ) || 0;
		var lit = 0, current = -1, frame = 0, dragging = false;

		play.disabled = false;
		fig.classList.add( 'is-ready' );

		function length() {
			return isFinite( audio.duration ) && audio.duration > 0 ? audio.duration : duration;
		}

		function paint( t ) {
			var d = length();
			var p = d ? Math.min( 1, Math.max( 0, t / d ) ) : 0;
			fig.style.setProperty( '--ab-p', ( p * 100 ).toFixed( 3 ) + '%' );
			fig.classList.toggle( 'is-started', t > 0 );

			// Only touch the bars whose state changed.
			var k = Math.round( p * bars.length );
			if ( k !== lit ) {
				var from = Math.min( k, lit ), to = Math.max( k, lit );
				for ( var i = from; i < to; i++ ) {
					bars[ i ].classList.toggle( 'is-played', i < k );
				}
				lit = k;
			}

			elapsed.textContent = fmt( t );
			wave.setAttribute( 'aria-valuenow', Math.round( t ) );
			wave.setAttribute( 'aria-valuetext', fmt( t ) + ' of ' + fmt( d ) );

			var c = -1;
			for ( var j = 0; j < marks.length; j++ ) {
				if ( t + 0.05 >= parseFloat( marks[ j ].getAttribute( 'data-t' ) ) ) {
					c = j;
				}
			}
			if ( c !== current ) {
				if ( marks[ current ] ) marks[ current ].classList.remove( 'is-current' );
				if ( marks[ c ] ) marks[ c ].classList.add( 'is-current' );
				if ( now ) now.textContent = marks[ c ] ? marks[ c ].textContent : '';
				current = c;
			}
		}

		function tick() {
			paint( audio.currentTime );
			frame = audio.paused ? 0 : requestAnimationFrame( tick );
		}

		function seek( t ) {
			var d = length();
			t = Math.min( Math.max( 0, t ), d ? d - 0.05 : 0 );
			audio.currentTime = t;
			paint( t );
		}

		function at( e ) {
			var r = wave.getBoundingClientRect();
			return Math.min( 1, Math.max( 0, ( e.clientX - r.left ) / r.width ) ) * length();
		}

		function toggle() {
			if ( audio.paused ) {
				var p = audio.play();
				if ( p && p.catch ) p.catch( function () {} );
			} else {
				audio.pause();
			}
		}

		play.addEventListener( 'click', toggle );

		audio.addEventListener( 'play', function () {
			players.forEach( function ( other ) {
				if ( other !== audio && ! other.paused ) other.pause();
			} );
			fig.classList.add( 'is-playing' );
			play.setAttribute( 'aria-label', 'Pause ' + title );
			if ( ! frame ) frame = requestAnimationFrame( tick );
			if ( 'mediaSession' in navigator && window.MediaMetadata ) {
				navigator.mediaSession.metadata = new MediaMetadata( { title: title, artist: ( fig.querySelector( '.ab-audio-meta' ) || {} ).textContent || '' } );
			}
		} );
		audio.addEventListener( 'pause', function () {
			fig.classList.remove( 'is-playing', 'is-loading' );
			play.setAttribute( 'aria-label', 'Play ' + title );
			paint( audio.currentTime );
		} );
		audio.addEventListener( 'waiting', function () { fig.classList.add( 'is-loading' ); } );
		audio.addEventListener( 'playing', function () { fig.classList.remove( 'is-loading' ); } );
		audio.addEventListener( 'ended', function () { audio.currentTime = 0; paint( 0 ); } );
		audio.addEventListener( 'loadedmetadata', function () {
			if ( total ) total.textContent = fmt( length() );
			wave.setAttribute( 'aria-valuemax', Math.round( length() ) );
		} );
		players.push( audio );

		// Scrub: press anywhere on the waveform and drag.
		wave.addEventListener( 'pointerdown', function ( e ) {
			if ( e.button !== 0 ) return;
			dragging = true;
			wave.setPointerCapture( e.pointerId );
			fig.classList.add( 'is-scrubbing' );
			seek( at( e ) );
		} );
		wave.addEventListener( 'pointermove', function ( e ) {
			var t = at( e );
			if ( dragging ) seek( t );
			if ( tip && e.pointerType === 'mouse' ) {
				tip.textContent = fmt( t );
				fig.style.setProperty( '--ab-h', ( t / ( length() || 1 ) * 100 ).toFixed( 3 ) + '%' );
			}
		} );
		function release() {
			dragging = false;
			fig.classList.remove( 'is-scrubbing' );
		}
		wave.addEventListener( 'pointerup', release );
		wave.addEventListener( 'pointercancel', release );

		wave.addEventListener( 'keydown', function ( e ) {
			var t = audio.currentTime, step = { ArrowLeft: -5, ArrowDown: -5, ArrowRight: 5, ArrowUp: 5, PageDown: -15, PageUp: 15 }[ e.key ];
			if ( step ) {
				seek( t + step );
			} else if ( e.key === 'Home' ) {
				seek( 0 );
			} else if ( e.key === 'End' ) {
				seek( length() );
			} else if ( e.key === ' ' || e.key === 'Enter' ) {
				toggle();
			} else {
				return;
			}
			e.preventDefault();
		} );

		marks.forEach( function ( mark ) {
			mark.addEventListener( 'click', function () {
				seek( parseFloat( mark.getAttribute( 'data-t' ) ) );
				if ( audio.paused ) toggle();
			} );
		} );
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.wp-block-anchor-audio' ), setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
