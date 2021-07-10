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

use LocalFile;
use MediaHandler;
use MediaTransformError;
use MediaTransformOutput;
use MediaWiki\Extension\WebP\WebPMediaHandler;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\MediaWikiServices;
use MWException;
use ThumbnailImage;

class LocalWebPFile extends LocalFile {

	/**
	 * Returns the correct media handler if the current file can be transformed to WebP
	 *
	 * @return bool|MediaHandler
	 */
	public function getHandler() {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getHandler();
		}

		wfDebugLog( 'WebP', "[LocalWebPFile::getHandler] Returning WebP handler for file {$this->getName()}" );

		if ( $this->handler !== null && $this->handler instanceof WebPMediaHandler ) {
			return $this->handler;
		}

		$this->handler = new WebPMediaHandler();

		return $this->handler;
	}

	/**
	 * Changes the extension to webp if the file is supported
	 *
	 * @return string
	 */
	public function getExtension() {
		if ( WebPTransformer::canTransform( $this ) ) {
			return 'webp';
		}

		return parent::getExtension();
	}

	/**
	 * Get the transformed image
	 * TODO: Return link to base webp file, if requested size > base size
	 *
	 * @param array $params
	 * @param int $flags
	 * @return bool|MediaTransformError|MediaTransformOutput|ThumbnailImage
	 */
	public function transform( $params, $flags = 0 ) {
		wfDebugLog( 'WebP', "[LocalWebPFile::transform] Running transform for file {$this->getName()}" );

		$transformed = parent::transform( $params, $flags );

		if ( $transformed === false ) {
			wfDebugLog( 'WebP', "[LocalWebPFile::transform] Parent returned false" );
		}

		if ( $transformed === false || !WebPTransformer::canTransform( $this ) || $transformed->getWidth() >= $this->getWidth() ) {
			wfDebugLog( 'WebP', "[LocalWebPFile::transform] Returning parent transform" );
			return $transformed;
		}

		$thumbName = $this->thumbName( $params );

		wfDebugLog( 'WebP', "[LocalWebPFile::transform] Thumbname is {$thumbName}" );

		$url = $this->getThumbUrl( $thumbName );
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'ThumbnailScriptPath' ) !== false ) {
			$url = $transformed->getUrl();
		}

		wfDebugLog( 'WebP', "[LocalWebPFile::transform] Thumbnail url is {$url}, path is {$this->getThumbPath( $thumbName )}" );

		return new ThumbnailImage( $this, $url, $this->getThumbPath( $thumbName ), [
			'width' => $transformed->getWidth(),
			'height' => $transformed->getHeight(),
		] );
	}

	/**
	 * Forces webp file to download
	 * Sets the name of the downloaded file
	 *
	 * @param string $thumbName
	 * @param string $dispositionType
	 * @return string
	 */
	public function getThumbDisposition( $thumbName, $dispositionType = 'inline' ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbDisposition( $thumbName, $dispositionType );
		}

		wfDebugLog( 'WebP', "[LocalWebPFile::getThumbDisposition] Running disposition for {$thumbName}" );

		$parts = [
			'attachment',
			"filename*=UTF-8''" . rawurlencode( basename( WebPTransformer::changeExtensionWebp( $thumbName ) ) ),
		];

		return implode( ';', $parts );
	}

	/**
	 * Returns the local file path
	 *
	 * @return bool|string
	 * @throws MWException
	 */
	public function getPath() {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getPath();
		}

		$zone = 'webp-public';

		if ( $this->repo->fileExists( $this->repo->getZonePath( $zone ) . '/' . $this->getRel() ) ) {
			wfDebugLog( 'WebP', "[LocalWebPFile::getPath] File exists on 'webp-public' under " . $this->repo->getZonePath( $zone ) . '/' . $this->getRel() );
			return $this->repo->getZonePath( $zone ) . '/' . $this->getRel();
		}

		if ( !isset( $this->path ) ) {
			$this->assertRepoDefined();
			$this->path = $this->repo->getZonePath( 'public' ) . '/' . $this->getRel();
		}

		wfDebugLog( 'WebP', "[LocalWebPFile::getPath] Returning path from public: " . $this->path );

		return $this->path;
	}

	/**
	 * Returns the url to the thumb
	 *
	 * @param false $suffix Thumb size
	 * @return string
	 */
	public function getThumbUrl( $suffix = false ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbUrl( $suffix );
		}

		$ext = $this->getExtension();
		$path = $this->repo->getZoneUrl( 'webp-thumb', $ext ) . '/' . $this->getUrlRel();

		wfDebugLog( 'WebP', "[LocalWebPFile::getThumbUrl] Path is {$path}" );

		if ( $suffix !== false ) {
			$path .= '/' . rawurlencode( $suffix );
			wfDebugLog( 'WebP', "[LocalWebPFile::getThumbUrl] Added suffix to path: {$path}" );
		}

		return $path;
	}

	/**
	 * Returns the path to the local thumb
	 *
	 * @param string|false $suffix
	 * @return string
	 */
	public function getThumbPath( $suffix = false ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbPath( $suffix );
		}

		if ( $suffix !== false ) {
			wfDebugLog( 'WebP', "[LocalWebPFile::getThumbPath] Suffix is {$suffix}" );
			$suffix = WebPTransformer::changeExtensionWebp( $suffix );
		}

		wfDebugLog( 'WebP', "[LocalWebPFile::getThumbPath] Returned Path is " . $this->repo->getZonePath( 'webp-thumb' ) . '/' . $this->getThumbRel( $suffix ) );

		return $this->repo->getZonePath( 'webp-thumb' ) . '/' . $this->getThumbRel( $suffix );
	}

}
