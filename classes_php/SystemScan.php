<?php
include("Config.php");

class SystemScan {

	private static function Write($k, $v) {
		if (is_array($v)) {
			foreach($v as $e) {
				self::Trace($k, $e);
			}
		} else {
			if ($v === NULL) {
				$v = "{NULL}";
			}

			echo $k."=".$v.Config::TraceLineEnding;
		}
	}

	public static function Trace($k, $v) {
		if (Config::IsTrace) {
			self::Write($k, $v);
		}
	}

	public static function Execute($cmd, &$output, &$ret) {
		if (!Config::BypassSystemExecute) {
			exec($cmd, $output, $ret);
		}

		self::Trace("cmd", $cmd);
		self::Trace("output", $output);
		self::Trace("return", $ret);
	}

	public static function Error($e) {
		self::Write("Error", $e);
	}

	public static function ScannerDevices() {
		$cmd = Config::Scanimage . " -L";
		SystemScan::Execute($cmd, $output, $ret);
		return $output;
	}
	
	public static function ScannerOptions() {
	    $cmd = Config::Scanimage . " -A";	
	    SystemScan::Execute($cmd, $output, $ret);
		return $output;
	}
    
    public static function HasConvert() {
	    $cmd = "which " . Config::Convert;	
	    SystemScan::Execute($cmd, $output, $ret);
		return count($output) > 0;
	}
}
?>
