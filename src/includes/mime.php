<?php
namespace Deadline;

class Mime {
	private static $mimemap, $inited = false;

	public static function type($ext) {
		if(!static::$inited) { static::init(); }
		return array_key_exists($ext, static::$mimemap) ? static::$mimemap[$ext] : 'application/octet-stream';
	}

	public static function ext($mime) {
		if(!static::$inited) { static::$init(); }
		foreach(static::$mimemap as $ext => $type) {
			if($mime === $type) {
				return $ext;
			}
		}
		return 'dat';
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
				'tar.bz2' => 'application/x-bzip-compressed-tar'
			);
		}
	}
}
