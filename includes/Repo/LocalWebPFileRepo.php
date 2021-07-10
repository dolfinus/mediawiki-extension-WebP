<?php

declare( strict_types=1 );

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WebP\Repo;

use FSFile;
use LocalRepo;
use MediaWiki\Extension\WebP\WebPTransformer;

class LocalWebPFileRepo extends LocalRepo {

	public function __construct( array $info = null ) {
		$this->fileFactory = [ LocalWebPFile::class, 'newFromTitle' ];
		$this->fileFactoryKey = [ LocalWebPFile::class, 'newFromKey' ];
		$this->fileFromRowFactory = [ LocalWebPFile::class, 'newFromRow' ];
		parent::__construct( $info );
	}

	/**
	 * Get the storage path corresponding to one of the zones
	 *
	 * @param string $zone
	 * @return string|null Returns null if the zone is not defined
	 */
	public function getZonePath( $zone ) {
		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getZonePath] Returning path for zone {$zone}" );

		if ( strpos( $zone, 'webp-' ) === false ) {
			return parent::getZonePath( $zone );
		}

		$zone = str_replace( 'webp-', '', $zone );

		[ $container, $base ] = $this->getZoneLocation( $zone );

		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getZonePath] Container is {$container}; Base is {$base}" );

		if ( $container === null || $base === null ) {
			return null;
		}

		$backendName = $this->backend->getName();

		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getZonePath] Backend name is {$backendName}" );

		if ( $base !== '' ) { // may not be set
			$base = "/{$base}";
		}

	   $container = sprintf( '%s/webp', $container );

		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getZonePath] Final Zone is " . "mwstore://$backendName/{$container}{$base}" );

		return "mwstore://$backendName/{$container}{$base}";
	}

	/**
	 * This is just a wrapper for the parent method, removing the '-webp' part
	 *
	 * @inheritDoc
	 */
	public function getZoneUrl( $zone, $ext = null ) {
		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getZoneUrl] Zone is {$zone}; Ext is {$ext}" );
		return parent::getZoneUrl( str_replace( 'webp-', '', $zone ), $ext );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function fileExists( $file ) {
		wfDebugLog( 'WebP', "[LocalWebPFileRepo::fileExists] Checking existence for {$file}" );

		$base = str_replace( 'webp/', '', $file );

		if ( strpos( $file, 'thumb' ) === false ) {
			$file = WebPTransformer::changeExtensionWebp( $file );
		}

		$baseExists = parent::fileExists( $base );
		$webpExists = parent::fileExists( $file );

		wfDebugLog( 'WebP', "[LocalWebPFileRepo::fileExists] Base: {$base}; WebP {$file}" );
		wfDebugLog( 'WebP', "[LocalWebPFileRepo::fileExists] Base exists: {$baseExists}; WebP Exists {$webpExists}" );

		return ( parent::fileExists( $base ) || parent::fileExists( $file ) );
	}

	/**
	 * @param string $virtualUrl
	 * @return FSFile|null
	 */
	public function getLocalReference( $virtualUrl ) {
		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getLocalReference] Virtual URL {$virtualUrl}" );

		if ( strpos( $virtualUrl, '/webp' ) !== false ) {
			wfDebugLog( 'WebP', "[LocalWebPFileRepo::getLocalReference] Virtual URL for WEBP " . WebPTransformer::changeExtensionWebp( $virtualUrl ) );
			$referenceWebP = parent::getLocalReference( WebPTransformer::changeExtensionWebp( $virtualUrl ) );

			if ( $referenceWebP !== null ) {
				return $referenceWebP;
			}
		}

		wfDebugLog( 'WebP', "[LocalWebPFileRepo::getLocalReference] Checking parent with URL " . str_replace( '/webp', '', $virtualUrl ) );

		return parent::getLocalReference( str_replace( '/webp', '', $virtualUrl ) );
	}
}
