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
	 * Get cached thumbnail URL for a given URL.
	 *
	 * @param string $url The URL of the site to get the thumbnail for.
	 * @return string The thumbnail URL.
	 */
	public static function get_cached_thumbnail_url( string $url ): string {
		$cache_key     = 'sprucely_mwpdn_thumbnail_url_' . md5( $url );
		$thumbnail_url = get_transient( $cache_key );

		if ( false === $thumbnail_url ) {
			$thumbnail_url = self::get_thumbnail_url( $url );
			set_transient( $cache_key, $thumbnail_url, WEEK_IN_SECONDS );
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
		$parsed_url = wp_parse_url( $url );
		$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];

		// Fetch the HTML content of the page.
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return ''; // Return an empty string if fetching the HTML fails.
		}

		$html = wp_remote_retrieve_body( $response );
		libxml_use_internal_errors( true ); // Handle HTML parsing errors gracefully.
		$dom = new \DOMDocument();
		$dom->loadHTML( $html );

		// Search for Open Graph image tags and standard favicon links.
		$meta_tags = $dom->getElementsByTagName( 'meta' );
		foreach ( $meta_tags as $meta ) {
			if ( $meta->getAttribute( 'property' ) === 'og:image' || $meta->getAttribute( 'name' ) === 'og:image' ) {
				return $meta->getAttribute( 'content' );
			}
		}

		$links = $dom->getElementsByTagName( 'link' );
		foreach ( $links as $link ) {
			if ( $link->getAttribute( 'rel' ) === 'icon' || $link->getAttribute( 'rel' ) === 'shortcut icon' ) {
				$favicon_url = $link->getAttribute( 'href' );
				if ( strpos( $favicon_url, 'http' ) === false ) {
					// Handle relative URLs.
					$favicon_url = $base_url . '/' . ltrim( $favicon_url, '/' );
				}
				return $favicon_url;
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
		$markdown = preg_replace( '/<strong>(.*?)<\/strong>/', '**$1**', $markdown );
		$markdown = preg_replace( '/<b>(.*?)<\/b>/', '**$1**', $markdown );
		$markdown = preg_replace( '/<em>(.*?)<\/em>/', '*$1*', $markdown );
		$markdown = preg_replace( '/<i>(.*?)<\/i>/', '*$1*', $markdown );
		$markdown = preg_replace( '/<code>(.*?)<\/code>/', '`$1`', $markdown );
		$markdown = preg_replace( '/<a(.*?)href="(.*?)"(.*?)>(.*?)<\/a>/', '[$4]($2)', $markdown );

		// Convert heading tags to Markdown.
		$markdown = preg_replace( '/<h1>(.*?)<\/h1>/', '# $1', $markdown );
		$markdown = preg_replace( '/<h2>(.*?)<\/h2>/', '## $1', $markdown );
		$markdown = preg_replace( '/<h3>(.*?)<\/h3>/', '### $1', $markdown );
		$markdown = preg_replace( '/<h4>(.*?)<\/h4>/', '#### $1', $markdown );
		$markdown = preg_replace( '/<h5>(.*?)<\/h5>/', '##### $1', $markdown );
		$markdown = preg_replace( '/<h6>(.*?)<\/h6>/', '###### $1', $markdown );

		// Convert list tags to Markdown.
		$markdown = preg_replace( '/<ul>/', "\n", $markdown );
		$markdown = preg_replace( '/<\/ul>/', "\n", $markdown );
		$markdown = preg_replace( '/<ol>/', "\n", $markdown );
		$markdown = preg_replace( '/<\/ol>/', '', $markdown );
		$markdown = preg_replace( '/<li>/', '- ', $markdown );
		$markdown = preg_replace( '/<\/li>/', '', $markdown );

		// Remove any remaining HTML tags.
		$markdown = wp_strip_all_tags( $markdown );

		return $markdown;
	}

	/**
	 * Send a message to the Discord webhook URL.
	 *
	 * @param array  $update       The update information.
	 * @param string $webhook_url  The webhook URL.
	 * @return bool True if the message was sent successfully, false otherwise.
	 */
	public static function send_discord_message( array $update, string $webhook_url ): bool {
		// Build the changelog summary if available.
		$changelog_summary = '';
		if ( ! empty( $update['changelog'] ) ) {
			$changelog_summary = self::convert_html_to_markdown( $update['changelog'] );
			$changelog_summary = mb_substr( $changelog_summary, 0, 850 ) . '...';
			$changelog_summary = "**Changelog Summary:** $changelog_summary\n";
		}

		// Build the description parts if available.
		$description   = ! empty( $update['description'] ) ? '**Description:** ' . self::convert_html_to_markdown( $update['description'] ) . "\n" : '';
		$author        = ! empty( $update['author'] ) ? '**Author:** ' . self::convert_html_to_markdown( $update['author'] ) . "\n" : '';
		$changelog_url = ! empty( $update['changelog_url'] ) ? "[View Full Changelog]({$update['changelog_url']})" : '';

		// Combine all parts of the description.
		$embed_description  = "**Version {$update['new_version']} is available.**\n\n";
		$embed_description .= $author;
		$embed_description .= $description;
		$embed_description .= $changelog_summary;
		$embed_description .= $changelog_url ? "\n\n{$changelog_url}" : '';

		// Build the embed array.
		$embed = array(
			'title'       => $update['plugin_name'] ?? $update['theme_name'],
			'description' => $embed_description,
		);

		if ( ! empty( $update['plugin_uri'] ) || ! empty( $update['theme_uri'] ) ) {
			$embed['url'] = $update['plugin_uri'] ?? $update['theme_uri'];
		}

		if ( ! empty( $update['thumbnail_url'] ) ) {
			$embed['thumbnail'] = array(
				'url' => $update['thumbnail_url'],
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

		$response = wp_remote_post( $webhook_url, $args );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Discord Webhook Error: ' . $response->get_error_message() );
			return false;
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			if ( wp_remote_retrieve_response_code( $response ) !== 204 ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Discord Webhook Response: ' . $response_body );
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'
					SELECT
						%s
					FROM
						%s wp
					WHERE
						%s = %d
					',
					$column_name,
					$table_name,
					$ignore_column,
					0
				)
			);

			wp_cache_set( $cache_key, $results, '', 300 ); // Cache for 5 minutes.
		}

		return $results ?: array();
	}
}
