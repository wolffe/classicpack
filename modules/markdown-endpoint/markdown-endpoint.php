<?php
/**
 * Markdown Endpoint — ClassicPack module.
 *
 * Serves a Markdown representation of any public, published post or page when
 * `.md` is appended to its URL (e.g. `/about.md`, `/about/index.md`, `/index.md`).
 * Intended for AI/LLM crawlers and other tools that prefer plain Markdown over HTML.
 *
 * Derived from getButterfly's "Markdown Endpoint" plugin (GPL).
 *
 * @package ClassicPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'parse_request', 'classicpack_markdown_endpoint_route', 0 );
add_action( 'wp_head', 'classicpack_markdown_endpoint_head_link', 1 );
add_filter( 'robots_txt', 'classicpack_markdown_endpoint_robots', 20, 2 );
add_action( 'save_post', 'classicpack_markdown_endpoint_flush_index' );
add_action( 'deleted_post', 'classicpack_markdown_endpoint_flush_index' );

/**
 * Detect `*.md` requests and serve a Markdown document instead of the normal template.
 *
 * @param WP $wp Main WP request object (unused; signature required by the hook).
 * @return void
 */
function classicpack_markdown_endpoint_route( $wp ) {
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path = wp_parse_url( $uri, PHP_URL_PATH );

	if ( ! $path ) {
		return;
	}

	$path = urldecode( $path );

	if ( strtolower( substr( $path, -3 ) ) !== '.md' ) {
		return;
	}

	// Strip subdirectory install prefix, if any.
	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( $home_path && '/' !== $home_path && strpos( $path, $home_path ) === 0 ) {
		$path = '/' . substr( $path, strlen( $home_path ) );
	}

	// /foo/index.md -> /foo/   |   /index.md -> /   |   /foo.md -> /foo/
	if ( preg_match( '#/index\.md$#i', $path ) ) {
		$slug_path = preg_replace( '#index\.md$#i', '', $path );
	} else {
		$slug_path = substr( $path, 0, -3 ) . '/';
	}

	$slug_path = '/' . ltrim( $slug_path, '/' );

	if ( '/' === $slug_path ) {
		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id ) {
			classicpack_markdown_endpoint_serve_post( $front_id );
		}
		classicpack_markdown_endpoint_serve_index();
	}

	$post_id = url_to_postid( home_url( $slug_path ) );

	if ( ! $post_id ) {
		// Let WordPress 404, but as text/markdown so a crawler does not parse an HTML error page.
		classicpack_markdown_endpoint_serve_404( $slug_path );
	}

	classicpack_markdown_endpoint_serve_post( $post_id );
}

/**
 * Output the Markdown document for a single post/page and exit.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function classicpack_markdown_endpoint_serve_post( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
		classicpack_markdown_endpoint_serve_404( get_permalink( $post_id ) );
	}

	$type = get_post_type_object( $post->post_type );
	if ( $type && empty( $type->public ) ) {
		classicpack_markdown_endpoint_serve_404( get_permalink( $post_id ) );
	}

	$cache_key = 'classicpack_md_' . $post->ID . '_' . md5( $post->post_modified_gmt );
	$body      = get_transient( $cache_key );

	if ( false === $body ) {
		$body = classicpack_markdown_endpoint_build_document( $post );
		set_transient( $cache_key, $body, WEEK_IN_SECONDS );
	}

	classicpack_markdown_endpoint_headers( 200 );
	header( 'Link: <' . get_permalink( $post ) . '>; rel="canonical"' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered Markdown document.
	exit;
}

/**
 * Build the Markdown document (front matter + body) for a post.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function classicpack_markdown_endpoint_build_document( $post ) {
	global $wp_query;

	// Give shortcodes / blocks a real loop context.
	$wp_query = new WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required so `the_content` filters run with real loop context.
		[
			'p'         => $post->ID,
			'post_type' => $post->post_type,
		]
	);
	if ( $wp_query->have_posts() ) {
		$wp_query->the_post();
	}

	$html = apply_filters( 'the_content', $post->post_content );

	wp_reset_postdata();

	$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';

	$out  = "---\n";
	$out .= 'title: ' . classicpack_markdown_endpoint_yaml( get_the_title( $post ) ) . "\n";
	$out .= 'source: ' . get_permalink( $post ) . "\n";
	if ( $excerpt ) {
		$out .= 'description: ' . classicpack_markdown_endpoint_yaml( wp_strip_all_tags( $excerpt ) ) . "\n";
	}
	$out .= 'updated: ' . get_post_modified_time( 'c', true, $post ) . "\n";
	$out .= "---\n\n";

	$out .= '# ' . wp_strip_all_tags( get_the_title( $post ) ) . "\n\n";
	$out .= classicpack_markdown_endpoint_html_to_markdown( $html ) . "\n";

	return $out;
}

/**
 * Output a Markdown index of pages and recent posts, and exit.
 *
 * @return void
 */
function classicpack_markdown_endpoint_serve_index() {
	$cache_key = 'classicpack_md_site_index';
	$body      = get_transient( $cache_key );

	if ( false === $body ) {
		$body = '# ' . wp_strip_all_tags( get_bloginfo( 'name' ) ) . "\n\n";
		$tag  = get_bloginfo( 'description' );
		if ( $tag ) {
			$body .= '> ' . wp_strip_all_tags( $tag ) . "\n\n";
		}

		$pages = get_pages(
			[
				'sort_column' => 'menu_order,post_title',
				'number'      => 200,
			]
		);

		if ( $pages ) {
			$body .= "## Pages\n\n";
			foreach ( $pages as $page ) {
				$body .= '- [' . wp_strip_all_tags( $page->post_title ) . '](' .
					untrailingslashit( get_permalink( $page ) ) . ".md)\n";
			}
			$body .= "\n";
		}

		$posts = get_posts( [ 'numberposts' => 100 ] );
		if ( $posts ) {
			$body .= "## Posts\n\n";
			foreach ( $posts as $recent_post ) {
				$body .= '- [' . wp_strip_all_tags( $recent_post->post_title ) . '](' .
					untrailingslashit( get_permalink( $recent_post ) ) . ".md)\n";
			}
		}

		set_transient( $cache_key, $body, DAY_IN_SECONDS );
	}

	classicpack_markdown_endpoint_headers( 200 );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered Markdown document.
	exit;
}

/**
 * Output a Markdown-flavoured 404 and exit.
 *
 * @param string $path Requested path, for the error message.
 * @return void
 */
function classicpack_markdown_endpoint_serve_404( $path ) {
	classicpack_markdown_endpoint_headers( 404 );
	echo "# 404 Not Found\n\n";
	echo 'No published content matches `' . esc_html( wp_strip_all_tags( $path ) ) . "`.\n\n";
	echo 'See [' . esc_url( home_url( '/index.md' ) ) . '](' . esc_url( home_url( '/index.md' ) ) . ") for an index.\n";
	exit;
}

/**
 * Send the shared headers for a Markdown response.
 *
 * @param int $status HTTP status code.
 * @return void
 */
function classicpack_markdown_endpoint_headers( $status ) {
	status_header( $status );
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: index, follow', true );
	header( 'Access-Control-Allow-Origin: *' );
	if ( 200 === $status ) {
		header( 'Cache-Control: public, max-age=3600, s-maxage=86400' );
	} else {
		header( 'Cache-Control: public, max-age=300' );
	}
}

/**
 * Quote a string for use as a YAML front-matter value.
 *
 * @param string $value Raw value.
 * @return string
 */
function classicpack_markdown_endpoint_yaml( $value ) {
	return '"' . str_replace( '"', '\"', trim( wp_strip_all_tags( $value ) ) ) . '"';
}

/**
 * Print a `<link rel="alternate">` to the Markdown version of the current singular page.
 *
 * @return void
 */
function classicpack_markdown_endpoint_head_link() {
	if ( ! is_singular() ) {
		return;
	}
	$url = untrailingslashit( get_permalink() ) . '.md';
	echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />' . "\n";
}

/**
 * Advertise the Markdown index in robots.txt.
 *
 * @param string $output Existing robots.txt output.
 * @param bool   $public Whether the site is public.
 * @return string
 */
function classicpack_markdown_endpoint_robots( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	$output .= "\n# Markdown mirrors: append .md to any URL\n";
	$output .= 'Sitemap: ' . home_url( '/index.md' ) . "\n";

	return $output;
}

/**
 * Clear the cached site index when content changes.
 *
 * @return void
 */
function classicpack_markdown_endpoint_flush_index() {
	delete_transient( 'classicpack_md_site_index' );
}

/**
 * Convert a block of rendered post HTML to Markdown.
 *
 * @param string $html Rendered HTML (post `the_content`).
 * @return string
 */
function classicpack_markdown_endpoint_html_to_markdown( $html ) {
	$html = preg_replace(
		'#<(script|style|noscript|iframe|svg|form|nav|button|select)\b[^>]*>.*?</\1>#is',
		'',
		$html
	);
	$html = preg_replace( '#<!--.*?-->#s', '', $html );

	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML(
		'<?xml encoding="utf-8" ?><div id="classicpack-md-root">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();

	$root = $dom->getElementById( 'classicpack-md-root' );
	if ( ! $root ) {
		return trim( wp_strip_all_tags( $html ) );
	}

	$md = classicpack_markdown_endpoint_walk( $root );
	$md = str_replace( "\xc2\xa0", ' ', $md );
	$md = preg_replace( '/[ \t]+\n/', "\n", $md );
	$md = preg_replace( '/\n{3,}/', "\n\n", $md );

	return trim( $md );
}

/**
 * Walk a DOM node's children and concatenate their Markdown.
 *
 * @param DOMNode $node Parent node.
 * @return string
 */
function classicpack_markdown_endpoint_walk( $node ) {
	$md = '';
	foreach ( $node->childNodes as $child ) {
		$md .= classicpack_markdown_endpoint_node( $child );
	}

	return $md;
}

/**
 * Convert a single DOM node to Markdown.
 *
 * @param DOMNode $node Node to convert.
 * @return string
 */
function classicpack_markdown_endpoint_node( $node ) {
	if ( XML_TEXT_NODE === $node->nodeType ) {
		return preg_replace( '/[ \t\r\n]+/', ' ', $node->nodeValue );
	}

	if ( XML_ELEMENT_NODE !== $node->nodeType ) {
		return '';
	}

	$tag = strtolower( $node->nodeName );

	switch ( $tag ) {
		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$text = trim( classicpack_markdown_endpoint_walk( $node ) );
			if ( '' === $text ) {
				return '';
			}

			return "\n\n" . str_repeat( '#', (int) substr( $tag, 1 ) ) . ' ' . $text . "\n\n";

		case 'p':
		case 'div':
		case 'section':
		case 'article':
		case 'header':
		case 'footer':
		case 'main':
		case 'aside':
		case 'figure':
		case 'figcaption':
			$text = classicpack_markdown_endpoint_walk( $node );
			if ( '' === trim( $text ) ) {
				return '';
			}

			return "\n\n" . trim( $text ) . "\n\n";

		case 'br':
			return "  \n";

		case 'hr':
			return "\n\n---\n\n";

		case 'strong':
		case 'b':
			$text = trim( classicpack_markdown_endpoint_walk( $node ) );

			return '' === $text ? '' : '**' . $text . '**';

		case 'em':
		case 'i':
			$text = trim( classicpack_markdown_endpoint_walk( $node ) );

			return '' === $text ? '' : '*' . $text . '*';

		case 'del':
		case 's':
			$text = trim( classicpack_markdown_endpoint_walk( $node ) );

			return '' === $text ? '' : '~~' . $text . '~~';

		case 'code':
			if ( $node->parentNode && 'pre' === strtolower( $node->parentNode->nodeName ) ) {
				return $node->textContent;
			}
			$text = trim( $node->textContent );

			return '' === $text ? '' : '`' . $text . '`';

		case 'pre':
			$lang = '';
			$cls  = $node->getAttribute( 'class' );
			if ( preg_match( '/(?:language|lang|brush:)[-\s:]([a-z0-9#+]+)/i', $cls, $m ) ) {
				$lang = strtolower( $m[1] );
			}

			return "\n\n```" . $lang . "\n" . rtrim( $node->textContent ) . "\n```\n\n";

		case 'blockquote':
			$inner = trim( classicpack_markdown_endpoint_walk( $node ) );
			if ( '' === $inner ) {
				return '';
			}
			$inner = preg_replace( '/^/m', '> ', $inner );

			return "\n\n" . $inner . "\n\n";

		case 'a':
			$href = trim( $node->getAttribute( 'href' ) );
			$text = trim( classicpack_markdown_endpoint_walk( $node ) );
			if ( '' === $text ) {
				return '';
			}
			if ( '' === $href || strpos( $href, '#' ) === 0 || stripos( $href, 'javascript:' ) === 0 ) {
				return $text;
			}

			return '[' . $text . '](' . classicpack_markdown_endpoint_abs_url( $href ) . ')';

		case 'img':
			$src = trim( $node->getAttribute( 'src' ) );
			if ( '' === $src ) {
				return '';
			}
			$alt = trim( $node->getAttribute( 'alt' ) );

			return '![' . $alt . '](' . classicpack_markdown_endpoint_abs_url( $src ) . ')';

		case 'ul':
		case 'ol':
			$list = classicpack_markdown_endpoint_list( $node );

			return '' === $list ? '' : "\n\n" . $list . "\n\n";

		case 'table':
			$table = classicpack_markdown_endpoint_table( $node );

			return '' === $table ? '' : "\n\n" . $table . "\n\n";

		case 'input':
		case 'textarea':
		case 'style':
		case 'script':
			return '';

		default:
			return classicpack_markdown_endpoint_walk( $node );
	}
}

/**
 * Convert a `<ul>`/`<ol>` node to a Markdown list.
 *
 * @param DOMElement $node List node.
 * @return string
 */
function classicpack_markdown_endpoint_list( $node ) {
	$ordered = ( 'ol' === strtolower( $node->nodeName ) );
	$start   = (int) $node->getAttribute( 'start' );
	$index   = $start > 0 ? $start : 1;
	$out     = '';

	foreach ( $node->childNodes as $li ) {
		if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
			continue;
		}

		$marker = $ordered ? ( $index . '. ' ) : '- ';
		$inner  = trim( classicpack_markdown_endpoint_walk( $li ) );
		$inner  = preg_replace( '/\n{2,}/', "\n", $inner );

		if ( '' === $inner ) {
			continue;
		}

		$lines = explode( "\n", $inner );
		$first = array_shift( $lines );
		$out  .= $marker . $first . "\n";

		foreach ( $lines as $line ) {
			$out .= ( '' === $line ? '' : '  ' . $line ) . "\n";
		}

		++$index;
	}

	return rtrim( $out, "\n" );
}

/**
 * Convert a `<table>` node to a Markdown table.
 *
 * @param DOMElement $node Table node.
 * @return string
 */
function classicpack_markdown_endpoint_table( $node ) {
	$rows = [];

	foreach ( $node->getElementsByTagName( 'tr' ) as $tr ) {
		$cells = [];
		foreach ( $tr->childNodes as $cell ) {
			if ( XML_ELEMENT_NODE !== $cell->nodeType ) {
				continue;
			}
			$name = strtolower( $cell->nodeName );
			if ( 'td' !== $name && 'th' !== $name ) {
				continue;
			}
			$text    = trim( preg_replace( '/\s+/', ' ', classicpack_markdown_endpoint_walk( $cell ) ) );
			$cells[] = str_replace( '|', '\|', $text );
		}
		if ( $cells ) {
			$rows[] = $cells;
		}
	}

	if ( ! $rows ) {
		return '';
	}

	$cols = count( $rows[0] );
	$out  = '| ' . implode( ' | ', $rows[0] ) . " |\n";
	$out .= '|' . str_repeat( ' --- |', $cols ) . "\n";

	$total = count( $rows );
	for ( $i = 1; $i < $total; $i++ ) {
		$row  = array_pad( array_slice( $rows[ $i ], 0, $cols ), $cols, '' );
		$out .= '| ' . implode( ' | ', $row ) . " |\n";
	}

	return rtrim( $out );
}

/**
 * Resolve a possibly-relative URL to an absolute site URL.
 *
 * @param string $url Raw URL from markup.
 * @return string
 */
function classicpack_markdown_endpoint_abs_url( $url ) {
	if ( preg_match( '#^(https?:)?//#i', $url ) || strpos( $url, 'mailto:' ) === 0 ) {
		return $url;
	}
	if ( strpos( $url, '/' ) === 0 ) {
		return home_url( $url );
	}

	return $url;
}
