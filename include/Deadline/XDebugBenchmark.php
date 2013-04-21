<?php
namespace Deadline;

use Psr\Log\LoggerInterface;

class XDebugBenchmark implements IBenchmark {
	private $snapshots = [];
	private $baseClassCount = 0;
	private $baseIFaceCount = 0;
	private $lastTime = 0;

	public function __construct() {
		if(!extension_loaded('xdebug')) {
			throw new RuntimeException('Tried to use XDebug-based benchmarking, but XDebug is not loaded!');
		}
		// subtract 2 from the class count to account for this class and App
		$this->baseClassCount = count(get_declared_classes()) - 2;
		$this->baseIFaceCount = count(get_declared_interfaces());
	}
	public function snapshot($name) {
		$time = xdebug_time_index();
		$this->snapshots[$name] = [
			'memory' => [
				'current' => xdebug_memory_usage(),
				'peak' => xdebug_peak_memory_usage()
			],
			'counts' => [
				'classes' => count(get_declared_classes()) - $this->baseClassCount,
				'interfaces' => count(get_declared_interfaces()) - $this->baseIFaceCount,
				'files' => count(get_included_files())
			],
			'time' => $time,
			'last_time' => $this->lastTime
		];
		$this->lastTime = $time;
	}

	private function memsize($bytes) {
		$sizes = ['b', 'kb', 'mb', 'gb'];
		$size  = 0;
		while($bytes > 1024) { $size++; $bytes /= 1024; }
		return number_format($bytes, 1) . $sizes[$size];
	}

	private function duration($s) {
		$sizes = ['s', 'ms', '&micro;s', 'ns', 'ps'];
		$size = 0;
		while($s < 1) { $size++; $s *= 1000; }
		return number_format($s, 2) . $sizes[$size];
	}

	private function row($name, $snapshot, $peak, $classes, $interfaces, $files, $duration) {
		$memory = $snapshot['memory'];
		$counts = $snapshot['counts'];
		$lastTime = $snapshot['last_time'];
		$time   = $snapshot['time'] - $lastTime;

		$peakpct  = ($memory['peak'] / $peak) * 100;
		$classpct = ($counts['classes'] / $classes) * 100;
		$ifacepct = ($counts['interfaces'] / $interfaces) * 100;
		$filepct  = ($counts['files'] / $files) * 100;
		$timepct  = ($time / $duration) * 100;

		$html = <<<END
<tr>
	<td class="name">%s</td>
	<td class="memory">%s</td>
	<td class="memory peak">%s <span class="percent">(%d%%)</span></td>
	<td class="time">%s <span class="percent">(%d%%)</span></td>
	<td class="classes">%d <span class="percent">(%d%%)</span></td>
	<td class="interfaces">%d <span class="percent">(%d%%)</span></td>
	<td class="files">%d <span class="percent">(%d%%)</span></td>
</tr>
END;
		return sprintf($html,
				$name,
				$this->memsize($memory['current']),
				$this->memsize($memory['peak']), $peakpct,
				$this->duration($time), $timepct,
				$counts['classes'], $classpct,
				$counts['interfaces'], $ifacepct,
				$counts['files'], $filepct
		);
	}

	public function output() {
		$peak       = xdebug_peak_memory_usage();
		$classes    = count(get_declared_classes()) - $this->baseClassCount;
		$interfaces = count(get_declared_interfaces()) - $this->baseIFaceCount;
		$files      = count(get_included_files());
		$duration   = xdebug_time_index();
		$this->snapshots['Total'] = [
			'memory' => [
				'current' => xdebug_memory_usage(),
				'peak' => $peak
			],
			'counts' => [
				'classes' => $classes,
				'interfaces' => $interfaces,
				'files' => $files
			],
			'time' => $duration,
			'last_time' => 0
		];

		$result = '';
		foreach($this->snapshots as $name => $snapshot) {
			$result .= $this->row($name, $snapshot, $peak, $classes, $interfaces, $files, $duration);
		}
		return <<<END
<style>
.benchmark-results {
	position: fixed;
	bottom: 3%;
	right: 3%;
	box-shadow:0 0 8px #555;
	background: white !important;
	padding: 5px;
	color: #000 !important;
	z-index: 9999;
	border-radius: 4px;
	border-collapse: initial;
	border-spacing: initial;
	font-size: 12px;
}
.benchmark-results th {
	padding: 5px;
	border: 1px solid black;
}
.benchmark-results tr {
	padding: 5px;
	border: 1px solid black;
}
.benchmark-results tr:nth-child(even) td {
	background: lightgray !important;
}
.benchmark-results tr:nth-child(odd) td {
	background: white !important;
}
.benchmark-results tr:hover td {
	background: blue !important;
	color: white;
}
.benchmark-results td {
	text-align: right;
}
.benchmark-results td.name {
	text-align: left;
}
.benchmark-results td.memory {
	width: 80px;
}
.benchmark-results td.peak {
	width: 90px;
}
.benchmark-results td.time {
	width: 90px;
}
.benchmark-results td.classes {
	width: 75px;
}
.benchmark-results td.interfaces {
	width: 75px;
}
.benchmark-results td.files {
	width: 75px;
}
.benchmark-results .percent {
	font-size: 9px;
	color: gray;
}
</style>
<table class="benchmark-results">
<thead>
	<tr>
		<th>Name</th>
		<th>Memory</th>
		<th>Peak (%)</th>
		<th>Time (%)</th>
		<th>Classes</th>
		<th>Interfaces</th>
		<th>Files</th>
	</tr>
</thead>
<tbody>
	$result
</tbody>
</table>
END;
	}
}