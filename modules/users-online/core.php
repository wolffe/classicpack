<?php

// ─── State ────────────────────────────────────────────────────────────────────

function classicpress_useronline_cached_count( ?int $set = null ): int {
    static $count = null;
    if ( null !== $set ) {
        $count = $set;
    }
    if ( null === $count ) {
        global $wpdb;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->useronline" );
    }
    return $count;
}

function classicpress_useronline_most( ?array $set = null ): array {
    static $most = null;
    if ( null !== $set ) {
        $most = $set;
    }
    return $most ?? [
        'count' => 1,
        'date'  => 0,
    ];
}

function classicpress_useronline_script_needed( bool $set = false ): bool {
    static $needed = false;
    if ( $set ) {
        $needed = true;
    }
    return $needed;
}

// ─── Setup ────────────────────────────────────────────────────────────────────

function classicpress_useronline_setup( array $most ): void {
    classicpress_useronline_most( $most );

    add_action( 'admin_head', 'classicpress_useronline_record' );
    add_action( 'wp_head', 'classicpress_useronline_record' );
    add_action( 'wp_footer', 'classicpress_useronline_scripts' );
    add_action( 'admin_footer', 'classicpress_useronline_scripts' );

    add_action( 'wp_ajax_classicpress_useronline', 'classicpress_useronline_ajax' );
    add_action( 'wp_ajax_nopriv_classicpress_useronline', 'classicpress_useronline_ajax' );

    add_filter( 'classicpress_useronline_display_user', 'classicpress_useronline_linked_name', 10, 2 );
}

// ─── Hooks ────────────────────────────────────────────────────────────────────

function classicpress_useronline_scripts(): void {
    if ( ! classicpress_useronline_script_needed() ) {
        return;
    }

    wp_enqueue_script( 'classicpress-useronline', plugins_url( 'useronline.js', __FILE__ ), [], CLASSICPACK_VERSION, true );
    wp_localize_script(
        'classicpress-useronline',
        'useronlineL10n',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'classicpress_useronline' ),
        ]
    );
}

function classicpress_useronline_record( string $page_url = '', string $page_title = '' ): void {
    require_once __DIR__ . '/bots.php';

    global $wpdb;

    if ( empty( $page_url ) ) {
        $page_url = wp_strip_all_tags( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
    }
    if ( empty( $page_title ) ) {
        $page_title = wp_strip_all_tags( classicpress_useronline_get_title() );
    }

    $referral   = wp_strip_all_tags( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
    $user_ip    = classicpress_useronline_get_ip();
    $user_agent = wp_strip_all_tags( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

    $user_id   = 0;
    $user_name = __( 'Guest', 'classicpack' );
    $user_type = 'guest';

    foreach ( classicpress_useronline_get_bots() as $name => $lookfor ) {
        if ( stristr( $user_agent, $lookfor ) !== false ) {
            $user_name = $name;
            $user_type = 'bot';
            break;
        }
    }

    if ( 'guest' === $user_type ) {
        $current_user = wp_get_current_user();
        if ( $current_user->ID ) {
            $user_id   = $current_user->ID;
            $user_name = $current_user->display_name;
            $user_type = 'member';
        } elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
            $user_name = trim( wp_strip_all_tags( (string) wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) );
        }
    }

    $timestamp = current_time( 'mysql', true );

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $wpdb->useronline
		 WHERE (user_id <> 0 AND user_id = %d)
		    OR (user_id = 0 AND user_agent = %s AND user_ip = %s)
		    OR (timestamp < DATE_SUB(%s, INTERVAL %d SECOND))",
            $user_id,
            $user_agent,
            $user_ip,
            $timestamp,
            300
        )
    );

    $wpdb->replace(
        $wpdb->useronline,
        stripslashes_deep(
            compact( 'timestamp', 'user_type', 'user_id', 'user_name', 'user_ip', 'user_agent', 'page_title', 'page_url', 'referral' )
        )
    );

    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->useronline" );
    classicpress_useronline_cached_count( $count );

    $most = classicpress_useronline_most();
    if ( $count > $most['count'] ) {
        $most = [
            'count' => $count,
            'date'  => time(),
        ];
        classicpress_useronline_most( $most );
        update_option( 'useronline_most', $most );
    }
}

function classicpress_useronline_ajax(): void {
    check_ajax_referer( 'classicpress_useronline', 'nonce' );

    $mode = sanitize_text_field( trim( wp_unslash( $_POST['mode'] ?? '' ) ) );

    if ( 'heartbeat' === $mode ) {
        $raw_page_url = isset( $_POST['page_url'] ) ? wp_unslash( $_POST['page_url'] ) : '';
        $page_url     = str_replace( get_bloginfo( 'url' ), '', $raw_page_url );
        $page_title   = sanitize_text_field( wp_unslash( $_POST['page_title'] ?? '' ) );
        if ( $page_url !== $raw_page_url ) {
            classicpress_useronline_record( $page_url, $page_title );
        }
        wp_die();
    }

    if ( 'details' === $mode ) {
        if ( ! current_user_can( 'list_users' ) ) {
            wp_die( '', '', 403 );
        }
        echo classicpress_useronline_render_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted HTML from classicpress_useronline_render_page(); escaped in this file.
    }

    wp_die();
}

function classicpress_useronline_linked_name( string $name, object $user ): string {
    if ( ! $user->user_id ) {
        return $name;
    }
    return '<a href="' . esc_url( get_author_posts_url( $user->user_id ) ) . '">' . $name . '</a>';
}

// ─── Internals ────────────────────────────────────────────────────────────────

function classicpress_useronline_get_title(): string {
    $site_name = get_bloginfo( 'name' );
    if ( is_admin() ) {
        $suffix = ' &raquo; ' . __( 'Admin', 'classicpack' ) . ' &raquo; ' . get_admin_page_title();
    } else {
        $suffix = trim( str_replace( $site_name, '', wp_get_document_title() ) );
        $suffix = trim( $suffix, " \t-–—\xc2\xa0" );
        $suffix = $suffix !== ''
            ? ' &raquo; ' . $suffix
            : ' &raquo; ' . wp_strip_all_tags( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
    }
    return $site_name . $suffix;
}

function classicpress_useronline_get_ip(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_TRUE_CLIENT_IP',       // Cloudflare Enterprise / Akamai
        'HTTP_X_REAL_IP',            // nginx
        'HTTP_X_FORWARDED_FOR',      // standard proxy (may be comma-separated)
        'HTTP_X_CLUSTER_CLIENT_IP',  // Rackspace / Riverbed
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ( $headers as $header ) {
        if ( empty( $_SERVER[ $header ] ) ) {
            continue;
        }
        $raw_header = (string) wp_unslash( $_SERVER[ $header ] );
        [ $ip ]     = explode( ',', $raw_header );
        $ip         = trim( $ip );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }
    }
    return '';
}

// ─── Public API (used by dashboard widget / AJAX) ─────────────────────────────

function classicpress_useronline_get_most_count(): int {
    return (int) classicpress_useronline_most()['count'];
}

function classicpress_useronline_get_most_date_string(): string {
    return wp_date(
        sprintf( __( '%1$s @ %2$s', 'classicpack' ), get_option( 'date_format' ), get_option( 'time_format' ) ),
        classicpress_useronline_most()['date']
    );
}

function classicpress_useronline_render_page(): string {
    global $wpdb;

    classicpress_useronline_script_needed( true );

    $buckets = [
        'member' => [],
        'guest'  => [],
        'bot'    => [],
    ];
    foreach ( $wpdb->get_results( "SELECT user_type, user_name, user_id, user_ip, user_agent, page_url, page_title, referral, timestamp FROM $wpdb->useronline ORDER BY timestamp DESC" ) as $user ) {
        if ( isset( $buckets[ $user->user_type ] ) ) {
            $buckets[ $user->user_type ][] = $user;
        }
    }
    $buckets = apply_filters( 'classicpress_useronline_buckets', $buckets );

    $counts         = array_map( 'count', $buckets );
    $counts['user'] = $counts['member'] + $counts['guest'] + $counts['bot'];

    $output = classicpress_useronline_widget_styles()
        . '<div id="useronline-details">'
        . '<div class="uo-stats">'
        . classicpress_useronline_badge( $counts['user'], __( 'Online', 'classicpack' ), 'online' )
        . classicpress_useronline_badge( $counts['member'], __( 'Members', 'classicpack' ), 'members' )
        . classicpress_useronline_badge( $counts['guest'], __( 'Guests', 'classicpack' ), 'guests' )
        . classicpress_useronline_badge( $counts['bot'], __( 'Bots', 'classicpack' ), 'bots' )
        . '</div>'
        . '<p class="uo-peak">' . sprintf(
            __( 'Peak: <strong>%1$s</strong> on <strong>%2$s</strong>', 'classicpack' ),
            number_format_i18n( classicpress_useronline_get_most_count() ),
            classicpress_useronline_get_most_date_string()
        ) . '</p>'
        . classicpress_useronline_detailed_list( $counts, $buckets )
        . '</div>';

    return apply_filters( 'classicpress_useronline_page', $output );
}

function classicpress_useronline_badge( int $count, string $label, string $modifier ): string {
    return '<span class="uo-badge uo-badge--' . $modifier . '">'
        . '<strong>' . number_format_i18n( $count ) . '</strong> '
        . esc_html( $label )
        . '</span>';
}

// ─── Formatting helpers ───────────────────────────────────────────────────────

function classicpress_useronline_detailed_list( array $counts, array $buckets ): string {
    if ( 0 === $counts['user'] ) {
        return '<p class="uo-empty">' . __( 'No one is online now.', 'classicpack' ) . '</p>';
    }

    // Prime the WP user-object cache for all members in one query so
    // subsequent get_userdata() calls inside classicpress_useronline_display_user don't
    // each fire their own SQL round-trip.
    if ( $counts['member'] > 0 ) {
        $member_ids = array_filter( array_column( $buckets['member'], 'user_id' ) );
        if ( $member_ids ) {
            get_users(
                [
                    'include' => $member_ids,
                    'fields'  => 'all',
                ]
            );
        }
    }

    $date_format = sprintf( __( '%1$s @ %2$s', 'classicpack' ), get_option( 'date_format' ), get_option( 'time_format' ) );
    $output      = '';

    foreach ( [ 'member', 'guest', 'bot' ] as $type ) {
        $count = $counts[ $type ];
        if ( ! $count ) {
            continue;
        }

        $output .= '<div class="uo-group uo-group--' . $type . 's">'
            . '<p class="uo-group-label"><span class="uo-dot"></span>'
            . esc_html( classicpress_useronline_format_count( $count, $type ) )
            . '</p>'
            . '<ul class="uo-list">';

        $i = 1;
        foreach ( $buckets[ $type ] as $user ) {
            $nr      = number_format_i18n( $i++ );
            $name    = apply_filters( 'classicpress_useronline_display_user', esc_html( $user->user_name ), $user );
            $time    = esc_html( (string) wp_date( $date_format, strtotime( $user->timestamp . ' UTC' ) ) );
            $initial = esc_html( mb_strtoupper( mb_substr( $user->user_name, 0, 1 ) ) );
            $avatar  = '<span class="uo-avatar">' . $initial . '</span>';

            $meta_parts = [];
            $ip = classicpress_useronline_format_ip( $user );
            if ( $ip ) {
                $meta_parts[] = $ip;
            }
            if ( current_user_can( 'edit_users' ) || ! str_contains( $user->page_url, 'wp-admin' ) ) {
                if ( ! empty( $user->page_url ) ) {
                    $meta_parts[] = '<a class="uo-meta-link" href="' . esc_url( $user->page_url ) . '" title="' . esc_attr( $user->page_title ) . '">'
                        . esc_html( $user->page_url ) . '</a>';
                }
                if ( ! empty( $user->referral ) ) {
                    $meta_parts[] = '<a class="uo-meta-link" href="' . esc_url( $user->referral ) . '">'
                        . esc_html( $user->referral ) . '</a>';
                }
            }

            $meta = $meta_parts
                ? '<span class="uo-meta">' . implode( ' <span class="uo-sep">·</span> ', $meta_parts ) . '</span>'
                : '';

            $row = '<li class="uo-user">'
                . $avatar
                . '<span class="uo-body"><span class="uo-name">' . $name . '</span>' . $meta . '</span>'
                . '<span class="uo-time">' . $time . '</span>'
                . '</li>';

            $output .= apply_filters( 'classicpress_useronline_custom_template', $row, $nr, $user );
        }

        $output .= '</ul></div>';
    }

    return $output;
}

function classicpress_useronline_format_count( int $count, string $type ): string {
    static $naming = [
        'user'    => '1 User',
        'users'   => '%COUNT% Users',
        'member'  => '1 Member',
        'members' => '%COUNT% Members',
        'guest'   => '1 Guest',
        'guests'  => '%COUNT% Guests',
        'bot'     => '1 Bot',
        'bots'    => '%COUNT% Bots',
    ];
    $key           = ( 1 === $count ) ? $type : $type . 's';
    return str_ireplace( '%COUNT%', number_format_i18n( $count ), $naming[ $key ] );
}

function classicpress_useronline_format_ip( object $user ): string {
    $ip = esc_html( $user->user_ip );
    if ( current_user_can( 'edit_users' ) && ! empty( $ip ) && 'unknown' !== $ip ) {
        return '<a class="uo-ip" dir="ltr" href="' . esc_url( 'https://whois.domaintools.com/' . $user->user_ip ) . '" title="' . esc_attr( $user->user_agent ) . '">' . $ip . '</a>';
    }
    return '';
}

// ─── Widget styles ────────────────────────────────────────────────────────────

function classicpress_useronline_widget_styles(): string {
    static $done = false;
    if ( $done ) {
        return '';
    }
    $done = true;

    return <<<'CSS'
<style id="uo-styles">
#useronline-details { font-size: 13px; line-height: 1.4; }

/* ── Stat badges ── */
.uo-stats { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 12px; }
.uo-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 10px;
	border-radius: 20px;
	font-size: 12px;
	line-height: 1;
}
.uo-badge strong { font-size: 12px; }
.uo-badge--online  { background: #eef3fa; color: #0a4b78; }
.uo-badge--members { background: #edfbf1; color: #0a5c26; }
.uo-badge--guests  { background: #fdf8ec; color: #7a4f00; }
.uo-badge--bots    { background: #f3f4f5; color: #50575e; }

/* ── Peak record ── */
.uo-peak { font-size: 12px; color: #787c82; margin: 0 0 16px !important; padding: 0 !important; }

/* ── Group ── */
.uo-group { margin: 0 0 14px; }
.uo-group:last-child { margin-bottom: 0; }
.uo-group-label {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .8px;
	color: #a7aaad;
	margin: 0 0 6px !important;
	padding: 0 !important;
}
.uo-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.uo-group--members .uo-dot { background: #00a32a; }
.uo-group--guests  .uo-dot { background: #dba617; }
.uo-group--bots    .uo-dot { background: #a7aaad; }

/* ── User list ── */
.uo-list { margin: 0 !important; padding: 0 !important; list-style: none !important; }
.uo-user {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 7px 0;
	border-top: 1px solid #f3f4f5;
}
.uo-user:first-child { border-top: none; }

/* ── Avatar ── */
.uo-avatar {
	width: 30px;
	height: 30px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 700;
	flex-shrink: 0;
	text-transform: uppercase;
}
.uo-group--members .uo-avatar { background: #dbeafe; color: #1e40af; }
.uo-group--guests  .uo-avatar { background: #fef9c3; color: #854d0e; }
.uo-group--bots    .uo-avatar { background: #f3f4f5; color: #787c82; }

/* ── Body ── */
.uo-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.uo-name { font-weight: 600; color: #1d2327; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.uo-name a { color: inherit; text-decoration: none; }
.uo-name a:hover { color: var(--wp-admin-theme-color, #2271b1); }
.uo-meta { font-size: 11px; color: #a7aaad; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.uo-sep { color: #dcdcde; }
.uo-meta-link, .uo-ip { color: #a7aaad; text-decoration: none; }
.uo-meta-link:hover, .uo-ip:hover { color: var(--wp-admin-theme-color, #2271b1); text-decoration: underline; }
.uo-ip { font-family: monospace; }

/* ── Time ── */
.uo-time { font-size: 11px; color: #c3c4c7; white-space: nowrap; flex-shrink: 0; margin-left: auto; }

/* ── Empty state ── */
.uo-empty { color: #a7aaad; font-style: italic; margin: 0 !important; }
</style>
CSS;
}
