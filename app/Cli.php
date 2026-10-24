<?php
/**
 * WP-CLI helpers for authoring Anchor Blocks.
 *
 * @package anchor-blocks
 */

if ( ! defined( 'ABSPATH' ) ) exit;

WP_CLI::add_command( 'anchor-blocks waveform',
/**
 * Prints the duration and waveform peaks for an anchor/audio block.
 *
 * Decodes the file with ffmpeg (local paths or URLs), so it works for
 * audio on hosts the editor's "Generate waveform" button can't read.
 *
 * ## OPTIONS
 *
 * <source>
 * : Audio file path or URL.
 *
 * [--bars=<n>]
 * : Number of bars. Default 160.
 *
 * ## EXAMPLES
 *
 *     wp anchor-blocks waveform https://example.com/song.mp3
 */
function ( $args, $assoc ) {
	$source = $args[0];
	$bars   = max( 16, min( 400, (int) ( $assoc['bars'] ?? 160 ) ) );
	$rate   = 8000;

	$cmd  = 'ffmpeg -v error -i ' . escapeshellarg( $source ) . ' -ac 1 -ar ' . $rate . ' -f s16le -';
	$proc = proc_open( $cmd, [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $proc ) ) {
		WP_CLI::error( 'Could not run ffmpeg.' );
	}
	$pcm = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	if ( 0 !== proc_close( $proc ) || strlen( $pcm ) < 2 ) {
		WP_CLI::error( 'ffmpeg failed: ' . trim( $err ) );
	}

	$samples = unpack( 's*', $pcm );
	$count   = count( $samples );
	$size    = $count / $bars;
	$rms     = [];
	for ( $b = 0; $b < $bars; $b++ ) {
		$from = (int) floor( $b * $size ) + 1;
		$to   = (int) floor( ( $b + 1 ) * $size );
		$sum  = 0.0;
		for ( $i = $from; $i <= $to; $i++ ) {
			$sum += $samples[ $i ] * $samples[ $i ];
		}
		$rms[] = sqrt( $sum / max( 1, $to - $from + 1 ) );
	}
	$max   = max( $rms ) ?: 1;
	$peaks = array_map( function ( $v ) use ( $max ) { return (int) round( $v / $max * 99 ); }, $rms );

	WP_CLI::line( wp_json_encode( [
		'duration' => round( $count / $rate, 2 ),
		'peaks'    => implode( ',', $peaks ),
	] ) );
} );
