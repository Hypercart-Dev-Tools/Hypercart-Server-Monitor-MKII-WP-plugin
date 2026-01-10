<?php
/**
 * Health Repository
 *
 * @package WP_Server_Monitor
 */

namespace WP_Server_Monitor\Persistence;

/**
 * Manages JSON file persistence for health data.
 */
class HealthRepository {
	/**
	 * JSON file name.
	 */
	const FILE_NAME = 'health-data.json';

	/**
	 * Temporary file suffix.
	 */
	const TMP_SUFFIX = '.tmp';

	/**
	 * Bad file suffix.
	 */
	const BAD_SUFFIX = '.bad';

	/**
	 * Get data directory path.
	 *
	 * @return string Directory path.
	 */
	private function get_data_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'wp-server-monitor';
	}

	/**
	 * Get JSON file path.
	 *
	 * @return string File path.
	 */
	private function get_file_path() {
		return trailingslashit( $this->get_data_dir() ) . self::FILE_NAME;
	}

	/**
	 * Ensure data directory exists.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function ensure_directory() {
		$dir = $this->get_data_dir();

		if ( is_dir( $dir ) ) {
			return true;
		}

		if ( ! wp_mkdir_p( $dir ) ) {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Failed to create data directory',
				array( 'dir' => $dir )
			);
			return false;
		}

		// Add .htaccess to prevent web access.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		\Hypercart_Logger::info(
			'wp-server-monitor',
			'Data directory created',
			array( 'dir' => $dir )
		);

		return true;
	}

	/**
	 * Read health data from JSON file.
	 *
	 * @return array Health data structure.
	 */
	public function read() {
		$file_path = $this->get_file_path();

		if ( ! file_exists( $file_path ) ) {
			return $this->get_empty_structure();
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Failed to read JSON file',
				array( 'file' => $file_path )
			);
			return $this->get_empty_structure();
		}

		$data = json_decode( $content, true );
		if ( null === $data ) {
			// Corruption detected - archive and start fresh.
			$this->archive_corrupted_file( $file_path );
			return $this->get_empty_structure();
		}

		return $data;
	}

	/**
	 * Write health data to JSON file (atomically).
	 *
	 * @param array $data Health data structure.
	 * @return bool True on success, false on failure.
	 */
	public function write( $data ) {
		if ( ! $this->ensure_directory() ) {
			return false;
		}

		$file_path = $this->get_file_path();
		$tmp_path  = $file_path . self::TMP_SUFFIX;

		// Encode JSON.
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Failed to encode JSON',
				array()
			);
			return false;
		}

		// Write to temporary file.
		$written = file_put_contents( $tmp_path, $json, LOCK_EX );
		if ( false === $written ) {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Failed to write temporary file',
				array( 'file' => $tmp_path )
			);
			return false;
		}

		// Atomic rename.
		if ( ! rename( $tmp_path, $file_path ) ) {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Failed to rename temporary file',
				array(
					'from' => $tmp_path,
					'to'   => $file_path,
				)
			);
			return false;
		}

		\Hypercart_Logger::debug(
			'wp-server-monitor',
			'JSON file written',
			array(
				'file'         => $file_path,
				'size_bytes'   => filesize( $file_path ),
				'sample_count' => count( $data['samples'] ?? array() ),
			)
		);

		return true;
	}

	/**
	 * Add a new sample to the data.
	 *
	 * @param array $sample Sample data.
	 * @return bool True on success, false on failure.
	 */
	public function add_sample( $sample ) {
		$data = $this->read();

		// Add new sample.
		$data['samples'][]   = $sample;
		$data['updated_utc'] = \Hypercart_Time::iso8601( \Hypercart_Time::now() );

		// Prune old samples (keep last 24 hours).
		$data['samples'] = $this->prune_old_samples( $data['samples'] );

		return $this->write( $data );
	}

	/**
	 * Prune samples older than 24 hours.
	 *
	 * @param array $samples Sample array.
	 * @return array Pruned samples.
	 */
	private function prune_old_samples( $samples ) {
		$cutoff = \Hypercart_Time::now() - ( 24 * HOUR_IN_SECONDS );

		$pruned = array_filter(
			$samples,
			function ( $sample ) use ( $cutoff ) {
				if ( ! isset( $sample['ts_utc'] ) ) {
					return false;
				}

				// Parse ISO8601 timestamp.
				$timestamp = strtotime( $sample['ts_utc'] );
				return $timestamp >= $cutoff;
			}
		);

		// Re-index array.
		$pruned = array_values( $pruned );

		$removed_count = count( $samples ) - count( $pruned );
		if ( $removed_count > 0 ) {
			\Hypercart_Logger::debug(
				'wp-server-monitor',
				'Pruned old samples',
				array(
					'removed' => $removed_count,
					'remaining' => count( $pruned ),
				)
			);
		}

		return $pruned;
	}

	/**
	 * Get empty data structure.
	 *
	 * @return array Empty structure.
	 */
	private function get_empty_structure() {
		return array(
			'version'     => 1,
			'updated_utc' => \Hypercart_Time::iso8601( \Hypercart_Time::now() ),
			'samples'     => array(),
		);
	}

	/**
	 * Archive corrupted file.
	 *
	 * @param string $file_path Original file path.
	 */
	private function archive_corrupted_file( $file_path ) {
		$bad_path = $file_path . self::BAD_SUFFIX . '.' . time();

		if ( rename( $file_path, $bad_path ) ) {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Corrupted JSON file archived',
				array(
					'original' => $file_path,
					'archived' => $bad_path,
				)
			);
		} else {
			\Hypercart_Logger::error(
				'wp-server-monitor',
				'Failed to archive corrupted file',
				array( 'file' => $file_path )
			);
		}
	}

	/**
	 * Get file status for debugging.
	 *
	 * @return array File status information.
	 */
	public function get_file_status() {
		$file_path = $this->get_file_path();
		$exists    = file_exists( $file_path );

		$status = array(
			'path'     => $file_path,
			'exists'   => $exists,
			'writable' => is_writable( $this->get_data_dir() ),
		);

		if ( $exists ) {
			$status['size_bytes']   = filesize( $file_path );
			$status['size_kb']      = round( $status['size_bytes'] / 1024, 2 );
			$status['modified_utc'] = filemtime( $file_path );

			$data                    = $this->read();
			$status['sample_count']  = count( $data['samples'] ?? array() );

			if ( ! empty( $data['samples'] ) ) {
				$timestamps = array_column( $data['samples'], 'ts_utc' );
				$status['oldest_sample'] = min( $timestamps );
				$status['newest_sample'] = max( $timestamps );
			}
		}

		return $status;
	}
}
