<?php
namespace Deadline;

use Analog;

class Mime {
	private static $mimemap, $compressmap, $inited = false;

	public static function type($ext) {
		if(!static::$inited) { static::init(); }
		return array_key_exists($ext, static::$mimemap) ? static::$mimemap[$ext] : 'application/octet-stream';
	}

	public static function ext($mime) {
		if(!static::$inited) { static::init(); }
		$semi = strpos($mime, ';');
		$mime = substr($mime, 0, $semi !== false ? $semi : strlen($mime));
		foreach(static::$mimemap as $ext => $type) {
			if($mime === $type) {
				return $ext;
			}
		}
		return 'dat';
	}

	public static function compress($mime) {
		$ext = static::ext($mime);
		return array_key_exists($ext, static::$compressmap) ? static::$compressmap[$ext] : false;
	}

	private static function init() {
		if(!static::$inited) {
			static::$inited = true;
			static::$mimemap = array(
				'pdf'     => 'application/pdf',
				'sig'     => 'application/pgp-signature',
				'spl'     => 'application/futuresplash',
				'class'   => 'application/octet-stream',
				'ps'      => 'application/postscript',
				'torrent' => 'application/x-bittorrent',
				'dvi'     => 'application/x-dvi',
				'gz'      => 'application/x-gzip',
				'pac'     => 'application/x-ns-proxy-autoconfig',
				'swf'     => 'application/x-shockwave-flash',
				'tar.gz'  => 'application/x-tgz',
				'tgz'     => 'application/x-tgz',
				'tar'     => 'application/x-tar',
				'zip'     => 'application/zip',
				'mp3'     => 'audio/mpeg',
				'm3u'     => 'audio/x-mpegurl',
				'wma'     => 'audio/x-ms-wma',
				'wax'     => 'audio/x-ms-wax',
				'ogg'     => 'application/ogg',
				'ogv'     => 'application/ogg',
				'wav'     => 'audio/x-wav',
				'gif'     => 'image/gif',
				'jpg'     => 'image/jpeg',
				'jpeg'    => 'image/jpeg',
				'png'     => 'image/png',
				'xbm'     => 'image/x-xbitmap',
				'xpm'     => 'image/x-xpixmap',
				'xwd'     => 'image/x-xwindowdump',
				'css'     => 'text/css',
				'html'    => 'text/html',
				'htm'     => 'text/html',
				'js'      => 'text/javascript',
				'asc'     => 'text/plain',
				'c'       => 'text/plain',
				'h'       => 'text/plain',
				'cc'      => 'text/plain',
				'cpp'     => 'text/plain',
				'hh'      => 'text/plain',
				'hpp'     => 'text/plain',
				'conf'    => 'text/plain',
				'log'     => 'text/plain',
				'text'    => 'text/plain',
				'txt'     => 'text/plain',
				'diff'    => 'text/plain',
				'patch'   => 'text/plain',
				'ebuild'  => 'text/plain',
				'eclass'  => 'text/plain',
				'rtf'     => 'application/rtf',
				'bmp'     => 'image/bmp',
				'tif'     => 'image/tiff',
				'tiff'    => 'image/tiff',
				'ico'     => 'image/x-icon',
				'dtd'     => 'text/xml',
				'xml'     => 'text/xml',
				'mpeg'    => 'video/mpeg',
				'mpg'     => 'video/mpeg',
				'mov'     => 'video/quicktime',
				'qt'      => 'video/quicktime',
				'avi'     => 'video/x-msvideo',
				'asf'     => 'video/x-ms-asf',
				'asx'     => 'video/x-ms-asf',
				'wmv'     => 'video/x-ms-wmv',
				'bz2'     => 'application/x-bzip',
				'tbz'     => 'application/x-bzip-compressed-tar',
				'tar.bz2' => 'application/x-bzip-compressed-tar',
				'webm'    => 'video/webm',
			);
			static::$compressmap = array(
				'pdf'     => false,
				'sig'     => true,
				'spl'     => false,
				'class'   => false,
				'ps'      => true,
				'torrent' => true,
				'dvi'     => false,
				'gz'      => false,
				'pac'     => false,
				'swf'     => false,
				'tar.gz'  => false,
				'tgz'     => false,
				'tar'     => true,
				'zip'     => false,
				'mp3'     => false,
				'm3u'     => true,
				'wma'     => false,
				'wax'     => false,
				'ogg'     => false,
				'ogv'     => false,
				'wav'     => true,
				'gif'     => false,
				'jpg'     => false,
				'jpeg'    => false,
				'png'     => false,
				'xbm'     => true,
				'xpm'     => true,
				'xwd'     => true,
				'css'     => true,
				'html'    => true,
				'htm'     => true,
				'js'      => true,
				'asc'     => true,
				'c'       => true,
				'h'       => true,
				'cc'      => true,
				'cpp'     => true,
				'hh'      => true,
				'hpp'     => true,
				'conf'    => true,
				'log'     => true,
				'text'    => true,
				'txt'     => true,
				'diff'    => true,
				'patch'   => true,
				'ebuild'  => true,
				'eclass'  => true,
				'rtf'     => true,
				'bmp'     => true,
				'tif'     => false,
				'tiff'    => false,
				'ico'     => true,
				'dtd'     => true,
				'xml'     => true,
				'mpeg'    => false,
				'mpg'     => false,
				'mov'     => false,
				'qt'      => false,
				'avi'     => false,
				'asf'     => false,
				'asx'     => false,
				'wmv'     => false,
				'bz2'     => false,
				'tbz'     => false,
				'tar.bz2' => false,
				'webm'    => false,
			);
		}
	}
}
