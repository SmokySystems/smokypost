<?php
// Usage: php extract.php <plugin dir> <text domain>  -> JSON of translatable strings.
$root = rtrim( $argv[1], '/' ); $domain = $argv[2];
$single = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
$out = array();
$add = function ( $msgid, $plural, $ref, $comment, $js ) use ( &$out ) {
	$k = $msgid . "\0" . $plural;
	if ( ! isset( $out[ $k ] ) ) { $out[ $k ] = array( 'msgid' => $msgid, 'plural' => $plural, 'refs' => array(), 'comment' => '', 'js' => false ); }
	$out[ $k ]['refs'][] = $ref;
	if ( $comment ) { $out[ $k ]['comment'] = $comment; }
	if ( $js ) { $out[ $k ]['js'] = true; }
};
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$list = array(); foreach ( $files as $f ) { $list[] = (string) $f; } sort( $list );
foreach ( $list as $file ) {
	$rel = substr( $file, strlen( $root ) + 1 );
	if ( preg_match( '#^(tests|tools|node_modules)/#', $rel ) ) continue;
	if ( substr( $file, -4 ) === '.php' ) {
		$t = token_get_all( file_get_contents( $file ) ); $comment = '';
		$n = count( $t );
		for ( $i = 0; $i < $n; $i++ ) {
			$tok = $t[ $i ];
			if ( is_array( $tok ) && T_COMMENT === $tok[0] && stripos( $tok[1], 'translators:' ) !== false ) { $comment = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', $tok[1] ) ); continue; }
			if ( ! is_array( $tok ) || T_STRING !== $tok[0] ) continue;
			$fn = $tok[1]; if ( ! in_array( $fn, $single, true ) && '_n' !== $fn ) continue;
			$j = $i + 1; while ( isset( $t[ $j ] ) && is_array( $t[ $j ] ) && in_array( $t[ $j ][0], array( T_WHITESPACE, T_COMMENT ), true ) ) { if ( T_COMMENT === $t[$j][0] && stripos( $t[$j][1], 'translators:' ) !== false ) { $comment = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', $t[$j][1] ) ); } $j++; }
			if ( ! isset( $t[ $j ] ) || '(' !== $t[ $j ] ) continue;
			$args = array(); $cur = array(); $depth = 0; $j++; $line = $tok[2];
			for ( ; isset( $t[ $j ] ); $j++ ) {
				$x = $t[ $j ];
				if ( is_array( $x ) && in_array( $x[0], array( T_WHITESPACE, T_COMMENT ), true ) ) { if ( T_COMMENT === $x[0] && stripos( $x[1], 'translators:' ) !== false ) { $comment = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', $x[1] ) ); } continue; }
				if ( '(' === $x || '[' === $x ) { $depth++; }
				if ( ( ')' === $x || ']' === $x ) ) { if ( 0 === $depth ) { $args[] = $cur; break; } $depth--; }
				if ( ',' === $x && 0 === $depth ) { $args[] = $cur; $cur = array(); continue; }
				$cur[] = $x;
			}
			$args = array_map( function ( $a ) use ( &$line ) { if ( 1 === count( $a ) && is_array( $a[0] ) && T_CONSTANT_ENCAPSED_STRING === $a[0][0] ) { $line = $a[0][2]; return eval( 'return ' . $a[0][1] . ';' ); } return null; }, $args );
			if ( '_n' === $fn ) { $args = array( $args[0] ?? null, $args[1] ?? null, $args[3] ?? null ); }
			if ( '_n' === $fn && is_string( $args[0] ) && $args[2] === $domain ) { $add( $args[0], $args[1], "$rel:$line", $comment, false ); $comment = ''; }
			elseif ( '_n' !== $fn && is_string( $args[0] ?? null ) && ( $args[1] ?? null ) === $domain ) { $add( $args[0], '', "$rel:$line", $comment, false ); $comment = ''; }
		}
	} elseif ( substr( $file, -3 ) === '.js' ) {
		$src = file_get_contents( $file );
		preg_match_all( "/__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'" . preg_quote( $domain ) . "'\s*\)/", $src, $m, PREG_OFFSET_CAPTURE );
		foreach ( $m[1] as $hit ) { $line = substr_count( substr( $src, 0, $hit[1] ), "\n" ) + 1; $add( stripcslashes( $hit[0] ), '', "$rel:$line", '', true ); }
	}
}
// Plugin header strings (WordPress translates Name and Description of the plugins list from the bundled .mo).
$head = file_get_contents( $root . '/smokypost.php', false, null, 0, 2048 );
foreach ( array( 'Plugin Name' => 'Name of the plugin', 'Description' => 'Description of the plugin' ) as $h => $c ) {
	if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $h, '/' ) . ':(.*)$/mi', $head, $m ) ) {
		$add( trim( $m[1] ), '', 'smokypost.php:' . ( substr_count( substr( $head, 0, strpos( $head, $m[0] ) ), "\n" ) + 1 ), $c, false );
	}
}
echo json_encode( array_values( $out ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
