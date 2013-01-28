<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

use Deadline\Mime;

class FileView implements View {
    private $file, $range = array(), $download = false;
    public function __construct($encoding = '', $reparse = false) {}
	public function prepare(Deadline\Response $response) {
		$flen = filesize($this->file);
		$this->range = array('start' => 0, 'end' => $flen - 1, 'length' => $flen);

		$response->setHeader('content type', Mime::type(pathinfo($this->file, PATHINFO_EXTENSION)));
		$response->setHeader('content length', $flen);
		// disable the time limit for this request
		set_time_limit(0);

		if($this->download) {
			$response->setHeader('content disposition', 'attachment; filename=' . basename($this->file));
			$response->setHeader('content transfer encoding', 'binary');
			$response->setHeader('accept ranges', 'bytes');
			$response->setHeader('content range', 'bytes ' . '0-' . ($flen - 1) . '/' . $flen);

			$range = App::request()->getHeader('Range');
			if($range != null) {
				// TODO support multi-range requests
				// how do they actually work? need to research this more
				$eq = strpos($range, '=');
				$dash = strpos($range, '-');
				$comma = strpos($range, ',');
				$comma = $comma === false ? strlen($range) : $comma;
				$start = (int)substr($range, $eq+1, $dash);
				$end = (int)substr($range, $dash, $comma);
				$end = $end === 0 ? $flen - 1 : $end;
				if($start < 0 || $end > $flen) {
					$response->setHeader('status', '416 Requested Range Not Satisfiable');
					$response->setHeader('content range', 'bytes */' . $flen);
				} else {
					$len = $end - $start;
					$response->setHeader('status', '206 Partial Content');
					$response->setHeader('content range', 'bytes ' . $start . '-' . $end . '/' . $flen);
					$response->setHeader('content length', $len, true);
					$this->range = array('start' => $start, 'end' => $end, 'length' => $len);
				}
			}
		}
	}
    public function output($fp) {
		$start = $this->range['start'];
		$length = $this->range['length'];
		$count = 0;

		$file = fopen($this->file, 'rb');
		stream_copy_to_stream($file, $fp, $length, $start);
		fclose($file);
	}
	public function getTemplate() { return $this->file; }
    public function setTemplate($template) { $this->setFile($template); }
	public function setFile($file, $download = false) {
		if(file_exists($file)) {
			$this->file = $file;
			$this->download = $download;
		}
	}
	public function __get($name) {}
	public function __set($name, $value) {}
}
