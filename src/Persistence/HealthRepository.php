<?php
/**
 * Health Repository
 *
 * @package Hypercart_Server_Monitor
 */

namespace Hypercart_Server_Monitor\Persistence;

/**
 * Manages JSON file persistence for health data.
 */
class HealthRepository {
	/**
	 * JSON file name.
	 */
	const FILE_NAME = 'health-data.json';

	/**
	 * Lock file name.
	 */
	const LOCK_FILE = 'health-data.lock';

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
		return trailingslashit( $upload_dir['basedir'] ) . 'hypercart-server-monitor';
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
	 * Get lock file path.
	 *
	 * @return string Lock file path.
	 */
	private function get_lock_path() {
		return trailingslashit( $this->get_data_dir() ) . self::LOCK_FILE;
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
				'hypercart-server-monitor',
				'Failed to create data directory',
				array( 'dir' => $dir )
			);
			return false;
		}

		// Add .htaccess to prevent web access (Apache).
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$htaccess_content = "# Hypercart Server Monitor - Deny all web access\n";
			$htaccess_content .= "<IfModule mod_authz_core.c>\n";
			$htaccess_content .= "    Require all denied\n";
			$htaccess_content .= "</IfModule>\n";
			$htaccess_content .= "<IfModule !mod_authz_core.c>\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</IfModule>\n";

			$result = file_put_contents( $htaccess, $htaccess_content );
			if ( false === $result ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Failed to create .htaccess protection',
					array( 'path' => $htaccess )
				);
			}
		}

		// Add index.html to prevent directory listing (works on all servers).
		$index_file = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index_file ) ) {
			$result = file_put_contents( $index_file, '<!-- Access denied -->' );
			if ( false === $result ) {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'Failed to create index.html protection',
					array( 'path' => $index_file )
				);
			}
		}

		\Hypercart_Logger::info(
			'hypercart-server-monitor',
			'Data directory created with security protections',
			array( 'dir' => $dir )
		);

		return true;
	}

	/**
	 * Read health data from JSON file.
	 *
	 * @param bool $allow_repair Whether to archive/repair corrupted files.
	 * @return array Health data structure.
	 */
	public function read( $allow_repair = true ) {
		$file_path = $this->get_file_path();

		if ( ! file_exists( $file_path ) ) {
			return $this->get_empty_structure();
		}

		// SECURITY: Check file size before reading (max 1MB).
		// With 24h pruning and 15-min intervals, file should be ~10-20KB max.
		// 1MB limit is a safety net against unexpected growth.
		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size > 1048576 ) {
			if ( $allow_repair ) {
				\Hypercart_Logger::error(
					'hypercart-server-monitor',
					'JSON file too large, archiving and resetting',
					array(
						'file' => $file_path,
						'size' => $file_size,
					)
				);
				$this->archive_corrupted_file( $file_path );
			} else {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'JSON file too large, repair deferred',
					array(
						'file' => $file_path,
						'size' => $file_size,
					)
				);
			}
			return $this->get_empty_structure();
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to read JSON file',
				array( 'file' => $file_path )
			);
			return $this->get_empty_structure();
		}

		$data = json_decode( $content, true );
		if ( null === $data ) {
			// Corruption detected - optionally archive and start fresh.
			if ( $allow_repair ) {
				$this->archive_corrupted_file( $file_path );
			} else {
				\Hypercart_Logger::warning(
					'hypercart-server-monitor',
					'JSON file corrupted, repair deferred',
					array( 'file' => $file_path )
				);
			}
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
				'hypercart-server-monitor',
				'Failed to encode JSON',
				array()
			);
			return false;
		}

		// Write to temporary file.
		$written = file_put_contents( $tmp_path, $json, LOCK_EX );
		if ( false === $written ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to write temporary file',
				array( 'file' => $tmp_path )
			);
			return false;
		}

		// Atomic rename.
		if ( ! rename( $tmp_path, $file_path ) ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to rename temporary file',
				array(
					'from' => $tmp_path,
					'to'   => $file_path,
				)
			);
			return false;
		}

		\Hypercart_Logger::debug(
			'hypercart-server-monitor',
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
		if ( ! $this->ensure_directory() ) {
			return false;
		}

		$lock_handle = fopen( $this->get_lock_path(), 'c' );
		if ( false === $lock_handle ) {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to open repository lock file',
				array( 'file' => $this->get_lock_path() )
			);
			return false;
		}

		if ( ! flock( $lock_handle, LOCK_EX ) ) {
			fclose( $lock_handle );
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
				'Failed to acquire repository lock',
				array( 'file' => $this->get_lock_path() )
			);
			return false;
		}

		try {
			$data = $this->read();

			// Add new sample.
			$data['samples'][]   = $sample;
			$data['updated_utc'] = \Hypercart_Time::iso8601( \Hypercart_Time::now() );

			// Prune old samples (keep last 24 hours).
			$data['samples'] = $this->prune_old_samples( $data['samples'] );

			return $this->write( $data );
		} finally {
			flock( $lock_handle, LOCK_UN );
			fclose( $lock_handle );
		}
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
				'hypercart-server-monitor',
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
				'hypercart-server-monitor',
				'Corrupted JSON file archived',
				array(
					'original' => $file_path,
					'archived' => $bad_path,
				)
			);
		} else {
			\Hypercart_Logger::error(
				'hypercart-server-monitor',
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

			$data                    = $this->read( false );
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
