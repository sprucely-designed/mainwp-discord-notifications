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
	 * Normalize a text value for Discord output.
	 *
	 * @param mixed $value      Raw value.
	 * @param bool  $multiline  Whether to preserve line breaks.
	 * @return string
	 */
	public static function sanitize_discord_text( $value, bool $multiline = false ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text = wp_strip_all_tags( wp_specialchars_decode( (string) $value ) );
		$text = preg_replace( '/\r\n?|\n/', "\n", $text );

		if ( ! $multiline ) {
			$text = preg_replace( '/\s+/', ' ', $text );
		} else {
			$text = preg_replace( '/[ \t]+/', ' ', $text );
			$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		}

		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		return self::escape_discord_markdown( $text );
	}

	/**
	 * Escape markdown control characters for Discord text fields.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	public static function escape_discord_markdown( string $text ): string {
		return preg_replace( '/([\\`*_{}\[\]()#+\-.!|>~])/', '\\\\$1', $text );
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
	 * Build an absolute URL from a remote document URL and a candidate asset URL.
	 *
	 * @param string $base_url      Base URL.
	 * @param string $candidate_url Candidate asset URL.
	 * @return string
	 */
	public static function normalize_remote_asset_url( string $base_url, string $candidate_url ): string {
		$candidate_url = trim( $candidate_url );
		if ( '' === $candidate_url ) {
			return '';
		}

		if ( 0 === strpos( $candidate_url, '//' ) ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			if ( ! is_string( $scheme ) || '' === $scheme ) {
				return '';
			}

			$candidate_url = $scheme . ':' . $candidate_url;
		} elseif ( ! preg_match( '#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $candidate_url ) ) {
			$candidate_url = untrailingslashit( $base_url ) . '/' . ltrim( $candidate_url, '/' );
		}

		return self::sanitize_remote_url( $candidate_url );
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

			if ( '' !== $thumbnail_url ) {
				set_transient( $cache_key, $thumbnail_url, WEEK_IN_SECONDS );
			}
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
		$markdown = preg_replace( '/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $html );
		$markdown = preg_replace( '/<(li|p|div|h[1-6])\b[^>]*>/i', '', $markdown );

		return self::sanitize_discord_text( $markdown, true );
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

		$title         = self::sanitize_discord_text( $update['plugin_name'] ?? $update['theme_name'] ?? '' );
		$new_version   = self::sanitize_discord_text( $update['new_version'] ?? '' );
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

		// Append the changelog path for GitHub-hosted plugins.
		if ( '' !== $changelog_url && preg_match( '#^https://github\.com/[^/]+/[^/]+/?$#i', $changelog_url ) ) {
			$changelog_url = trailingslashit( $changelog_url ) . 'blob/main/CHANGELOG.md';
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
