<?php
/**
 * Helper functions for Discord Webhook Notifications for MainWP.
 *
 * @package Sprucely_MainWP_Discord
 */

namespace Sprucely\MainWP_Discord;

/**
 * Class Helpers
 */
class Helpers {

	/**
	 * Normalize a text value using WordPress sanitizers.
	 *
	 * @param mixed $value      Raw value.
	 * @param bool  $multiline  Whether to preserve line breaks.
	 * @return string
	 */
	public static function sanitize_text_value( $value, bool $multiline = false ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text = wp_specialchars_decode( (string) $value, ENT_QUOTES );
		$text = $multiline ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );

		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		return $text;
	}

	/**
	 * Validate and normalize a remote URL.
	 *
	 * @param mixed $url URL to validate.
	 * @return string
	 */
	public static function sanitize_remote_url( $url ): string {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return '';
		}

		$parsed_url = wp_parse_url( $url );
		if ( empty( $parsed_url['scheme'] ) || empty( $parsed_url['host'] ) ) {
			return '';
		}

		$scheme = strtolower( $parsed_url['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Normalize a remote URL that may be absolute, root-relative, or path-relative.
	 *
	 * @param string $base_url      Base URL used for resolving relative paths.
	 * @param mixed  $candidate_url Candidate URL.
	 * @return string
	 */
	public static function normalize_remote_reference_url( string $base_url, $candidate_url ): string {
		if ( ! is_scalar( $candidate_url ) ) {
			return '';
		}

		$candidate_url = trim( (string) $candidate_url );
		if ( '' === $candidate_url ) {
			return '';
		}

		$normalized_url = self::sanitize_remote_url( $candidate_url );
		if ( '' !== $normalized_url ) {
			return $normalized_url;
		}

		$base_url = self::sanitize_remote_url( $base_url );
		if ( '' === $base_url ) {
			return '';
		}

		$base_parts = wp_parse_url( $base_url );
		if ( empty( $base_parts['scheme'] ) || empty( $base_parts['host'] ) ) {
			return '';
		}

		if ( 0 === strpos( $candidate_url, '//' ) ) {
			$candidate_url = $base_parts['scheme'] . ':' . $candidate_url;
		} elseif ( 0 === strpos( $candidate_url, '/' ) ) {
			$candidate_url = $base_parts['scheme'] . '://' . $base_parts['host'] . $candidate_url;
		} else {
			$candidate_url = untrailingslashit( $base_url ) . '/' . ltrim( $candidate_url, '/' );
		}

		return self::sanitize_remote_url( $candidate_url );
	}

	/**
	 * Build an absolute URL from a remote document URL and a candidate asset URL.
	 *
	 * @param string $base_url      Base URL.
	 * @param string $candidate_url Candidate asset URL.
	 * @return string
	 */
	public static function normalize_remote_asset_url( string $base_url, string $candidate_url ): string {
		return self::normalize_remote_reference_url( $base_url, $candidate_url );
	}

	/**
	 * Get cached thumbnail URL for a given URL.
	 *
	 * @param string $url The URL of the site to get the thumbnail for.
	 * @return string The thumbnail URL.
	 */
	public static function get_cached_thumbnail_url( string $url ): string {
		$url = self::sanitize_remote_url( $url );
		if ( '' === $url ) {
			return '';
		}

		$cache_key     = 'sprucely_mwpdn_thumbnail_url_' . md5( $url );
		$thumbnail_url = get_transient( $cache_key );

		if ( false === $thumbnail_url ) {
			$thumbnail_url = self::get_thumbnail_url( $url );
			set_transient( $cache_key, $thumbnail_url, '' === $thumbnail_url ? HOUR_IN_SECONDS : WEEK_IN_SECONDS );
		}

		return $thumbnail_url;
	}

	/**
	 * Get the thumbnail URL from the site's HTML.
	 *
	 * @param string $url The URL of the site to get the thumbnail for.
	 * @return string The thumbnail URL.
	 */
	public static function get_thumbnail_url( string $url ): string {
		$url = self::sanitize_remote_url( $url );
		if ( '' === $url ) {
			return '';
		}

		$parsed_url = wp_parse_url( $url );
		if ( empty( $parsed_url['scheme'] ) || empty( $parsed_url['host'] ) ) {
			return '';
		}

		$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];

		// Fetch the HTML content of the page.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return ''; // Return an empty string if fetching the HTML fails.
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$html = wp_remote_retrieve_body( $response );
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}

		$previous_errors = libxml_use_internal_errors( true ); // Handle HTML parsing errors gracefully.
		$dom = new \DOMDocument();

		try {
			$loaded = $dom->loadHTML( $html );
		} catch ( \ValueError $error ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_errors );
			return '';
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( false === $loaded ) {
			return '';
		}

		// Search for Open Graph image tags and standard favicon links.
		$meta_tags = $dom->getElementsByTagName( 'meta' );
		foreach ( $meta_tags as $meta ) {
			if ( $meta instanceof \DOMElement ) {
				if ( $meta->getAttribute( 'property' ) === 'og:image' || $meta->getAttribute( 'name' ) === 'og:image' ) {
					return self::normalize_remote_asset_url( $base_url, $meta->getAttribute( 'content' ) );
				}
			}
		}

		$links = $dom->getElementsByTagName( 'link' );
		foreach ( $links as $link ) {
			if ( $link instanceof \DOMElement ) {
				if ( $link->getAttribute( 'rel' ) === 'icon' || $link->getAttribute( 'rel' ) === 'shortcut icon' ) {
					return self::normalize_remote_asset_url( $base_url, $link->getAttribute( 'href' ) );
				}
			}
		}

		return ''; // Return an empty string if no image is found.
	}

	/**
	 * Convert HTML to Discord-supported Markdown.
	 *
	 * @param string $html The HTML content.
	 * @return string The Markdown content.
	 */
	public static function convert_html_to_markdown( string $html ): string {
		// Convert common HTML tags to Markdown.
		$markdown = $html;
		$markdown = preg_replace( '/<strong>(.*?)<\/strong>/is', '**$1**', $markdown );
		$markdown = preg_replace( '/<b>(.*?)<\/b>/is', '**$1**', $markdown );
		$markdown = preg_replace( '/<em>(.*?)<\/em>/is', '*$1*', $markdown );
		$markdown = preg_replace( '/<i>(.*?)<\/i>/is', '*$1*', $markdown );
		$markdown = preg_replace( '/<code>(.*?)<\/code>/is', '`$1`', $markdown );
		$markdown = preg_replace_callback(
			'/<a(.*?)href="(.*?)"(.*?)>(.*?)<\/a>/is',
			static function ( array $matches ): string {
				$link_url  = self::sanitize_remote_url( $matches[2] ?? '' );
				$link_text = trim( wp_strip_all_tags( $matches[4] ?? '' ) );

				if ( '' === $link_text ) {
					return '';
				}

				if ( '' === $link_url ) {
					return $link_text;
				}

				return '[' . $link_text . '](' . $link_url . ')';
			},
			$markdown
		);

		// Convert heading tags to Markdown.
		$markdown = preg_replace( '/<h1>(.*?)<\/h1>/is', '# $1', $markdown );
		$markdown = preg_replace( '/<h2>(.*?)<\/h2>/is', '## $1', $markdown );
		$markdown = preg_replace( '/<h3>(.*?)<\/h3>/is', '### $1', $markdown );
		$markdown = preg_replace( '/<h4>(.*?)<\/h4>/is', '#### $1', $markdown );
		$markdown = preg_replace( '/<h5>(.*?)<\/h5>/is', '##### $1', $markdown );
		$markdown = preg_replace( '/<h6>(.*?)<\/h6>/is', '###### $1', $markdown );

		// Convert list tags to Markdown.
		$markdown = preg_replace( '/<ul>/', "\n", $markdown );
		$markdown = preg_replace( '/<\/ul>/', "\n", $markdown );
		$markdown = preg_replace( '/<ol>/', "\n", $markdown );
		$markdown = preg_replace( '/<\/ol>/', '', $markdown );
		$markdown = preg_replace( '/<li>/', '- ', $markdown );
		$markdown = preg_replace( '/<\/li>/', '', $markdown );

		// Remove any remaining HTML tags.
		$markdown = wp_strip_all_tags( $markdown );
		$markdown = sanitize_textarea_field( $markdown );
		$markdown = preg_replace( '/\r\n?|\n/', "\n", $markdown );
		$markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

		return trim( $markdown );
	}

	/**
	 * Send a message to the Discord webhook URL.
	 *
	 * @param array  $update       The update information.
	 * @param string $webhook_url  The webhook URL.
	 * @return bool True if the message was sent successfully, false otherwise.
	 */
	public static function send_discord_message( array $update, string $webhook_url ): bool {
		$webhook_url = self::sanitize_remote_url( $webhook_url );
		if ( '' === $webhook_url ) {
			return false;
		}

		$title         = self::sanitize_text_value( $update['plugin_name'] ?? $update['theme_name'] ?? '' );
		$new_version   = self::sanitize_text_value( $update['new_version'] ?? '' );
		$description   = self::convert_html_to_markdown( (string) ( $update['description'] ?? '' ) );
		$author        = self::convert_html_to_markdown( (string) ( $update['author'] ?? '' ) );
		$changelog     = self::convert_html_to_markdown( (string) ( $update['changelog'] ?? '' ) );
		$changelog_url = self::sanitize_remote_url( $update['changelog_url'] ?? '' );
		$embed_url     = self::sanitize_remote_url( $update['plugin_uri'] ?? $update['theme_uri'] ?? '' );
		$thumbnail_url = self::sanitize_remote_url( $update['thumbnail_url'] ?? '' );

		if ( '' === $title || '' === $new_version ) {
			return false;
		}

		// Build the changelog summary if available.
		$changelog_summary = '';
		if ( '' !== $changelog ) {
			$changelog_summary = $changelog;
			$changelog_summary = mb_substr( $changelog_summary, 0, 850 ) . '...';
			$changelog_summary = "**Changelog Summary:** $changelog_summary\n";
		}

		// Append the anchor tag for the correct changelog tab for plugins on wp.org.
		if ( '' !== $changelog_url && false !== strpos( $changelog_url, 'wordpress.org/plugins' ) ) {
			// Ensure the URL ends with a trailing slash then append the anchor.
			$changelog_url = trailingslashit( $changelog_url ) . '#developers';
		}

		// Build the description parts if available.
		$description   = '' !== $description ? '**Description:** ' . $description . "\n" : '';
		$author        = '' !== $author ? '**Author:** ' . $author . "\n" : '';
		$changelog_url = '' !== $changelog_url ? "[View Full Changelog]({$changelog_url})" : '';

		// Combine all parts of the description.
		$embed_description  = "**Version {$new_version} is available.**\n\n";
		$embed_description .= $author;
		$embed_description .= $description;
		$embed_description .= $changelog_summary;
		$embed_description .= $changelog_url ? "\n\n{$changelog_url}" : '';

		// Build the embed array.
		$embed = array(
			'title'       => $title,
			'description' => $embed_description,
		);

		if ( '' !== $embed_url ) {
			$embed['url'] = $embed_url;
		}

		if ( '' !== $thumbnail_url ) {
			$embed['thumbnail'] = array(
				'url' => $thumbnail_url,
			);
		}

		$payload = array(
			'content' => '',
			'embeds'  => array( $embed ),
		);

		$args = array(
			'body'        => wp_json_encode( $payload ),
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'method'      => 'POST',
			'data_format' => 'body',
		);

		if ( false === $args['body'] ) {
			return false;
		}

		$response = wp_remote_post( $webhook_url, $args );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Discord Webhook Error: ' . $response->get_error_message() );
			return false;
		} else {
			if ( wp_remote_retrieve_response_code( $response ) !== 204 ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Discord Webhook Response Code: ' . (string) wp_remote_retrieve_response_code( $response ) );
				return false;
			}
			return true;
		}
	}

	/**
	 * Query the MainWP database for updates.
	 *
	 * @param string $type The type of update (plugin or theme).
	 * @param string $table_name The table name.
	 * @param string $column_name The column name.
	 * @param string $ignore_column The ignore column name.
	 * @return array The query results.
	 */
	public static function query_mainwp_db( string $type, string $table_name, string $column_name, string $ignore_column ): array {
		global $wpdb;

		$cache_key = 'sprucely_mwpdn_' . $type . '_updates';
		$results   = wp_cache_get( $cache_key );

		if ( false === $results ) {
			// Build SQL safely for identifiers (coming from internal constants only).
			$column       = preg_replace( '/[^a-zA-Z0-9_]/', '', $column_name );
			$table        = preg_replace( '/[^a-zA-Z0-9_]/', '', $table_name );
			$ignore_col   = preg_replace( '/[^a-zA-Z0-9_]/', '', $ignore_column );
			$sql          = "SELECT {$column} FROM {$table} wp WHERE {$ignore_col} = %d";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared_sql = $wpdb->prepare( $sql, 0 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results      = $wpdb->get_results( $prepared_sql );

			wp_cache_set( $cache_key, $results, '', 300 ); // Cache for 5 minutes.
		}

		return $results ?: array();
	}

	/**
	 * Get sent notifications from options by type.
	 *
	 * @param 'plugin'|'theme' $type Type of updates.
	 * @return array Array of sent notifications.
	 */
	public static function get_sent_notifications( string $type ): array {
		$option_name = ( 'theme' === $type ) ? 'sprucely_mwpdn_sent_theme_notifications' : 'sprucely_mwpdn_sent_plugin_notifications';
		return get_option( $option_name, array() );
	}

	/**
	 * Update sent notifications in options by type.
	 *
	 * @param 'plugin'|'theme' $type Type of updates.
	 * @param array            $notifications Array of sent notifications.
	 */
	public static function update_sent_notifications( string $type, array $notifications ): void {
		$option_name = ( 'theme' === $type ) ? 'sprucely_mwpdn_sent_theme_notifications' : 'sprucely_mwpdn_sent_plugin_notifications';
		update_option( $option_name, $notifications );
	}

	/**
	 * Check if notification was already sent for this plugin/theme version.
	 *
	 * @param 'plugin'|'theme' $type Type of updates.
	 * @param string           $slug Plugin or theme slug.
	 * @param string           $version Version number.
	 * @return bool True if already sent, false otherwise.
	 */
	public static function is_notification_sent( string $type, string $slug, string $version ): bool {
		$sent_notifications = self::get_sent_notifications( $type );
		return isset( $sent_notifications[ $slug ] ) && version_compare( $sent_notifications[ $slug ], $version, '>=' );
	}

	/**
	 * Mark notification as sent for this plugin/theme version.
	 *
	 * @param 'plugin'|'theme' $type Type of updates.
	 * @param string           $slug Plugin or theme slug.
	 * @param string           $version Version number.
	 */
	public static function mark_notification_sent( string $type, string $slug, string $version ): void {
		$sent_notifications           = self::get_sent_notifications( $type );
		$sent_notifications[ $slug ]  = $version;
		self::update_sent_notifications( $type, $sent_notifications );
	}
}
