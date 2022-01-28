<?php
include("SystemScan.php");

class ScannerOption {
    public $name = "";
    public $values = "";
    public $isRange = false;
    public $defaultValue = "";
    
    public function isValidValue ($value) {
        if ($this->isRange) {
            $last = (count($this->values) > 0) ? count($this->values)-1 : 0;
            return $value >= $this->values[0] && $value <= $this->values[$last];
        } else {
            return in_array($value, $this->values);
        }
    }
}

final class ScannerOptions {
    const DEVICES_FILE = "./devices.txt";
    const OPTIONS_FILE = "./scanimage.txt";
    private static $options = NULL;
    
    public static function getAll() {
        if (self::$options == NULL) 
            self::$options = self::parseOptions();       
            
        return self::$options;
    }
    
    public static function get($device) {
        $scanners = self::getAll();
        
        for ($i = 0; $i < count($scanners); ++$i) {
            if ($scanners[$i]["name"] == $device) {
                return $scanners[$i];
            }
        }
        
        return $scanners[0]; 
    }

    private static function parseOptions($device = "") {
        // Check if file was generated by 'scanimage -L'. Otherwise, invoke it and save output to file
        $deviceList = array();
        if (file_exists(self::DEVICES_FILE)) { 
            $deviceList = explode("\n", file_get_contents(self::DEVICES_FILE));
        } else {
            $deviceList = SystemScan::ScannerDevices();
            file_put_contents(self::DEVICES_FILE, implode("\n", $deviceList));
        }
        
        // Check if file was generated by 'scanimage -A'. Otherwise, invoke it and save output to file
        $output = array();
        if (file_exists(self::OPTIONS_FILE)) { 
            $output = explode("\n", file_get_contents(self::OPTIONS_FILE));
        } else {
            $output = SystemScan::ScannerOptions();
            file_put_contents(self::OPTIONS_FILE, implode("\n", $deviceList));
        }
                
        $scannersPattern = "/^[ ]*device `(.+)' is a (.+)/";
        $matchedScanners = preg_grep($scannersPattern, $deviceList);
        $scanners = array();
        
        // Store scanner name (key) and description (value)
        foreach ($matchedScanners as $matched) {
            preg_match($scannersPattern, $matched, $matched);
            $name = $matched[1];
            $description = $matched[2];
            $devices[$name] = $description;
        }

        $devicePattern = "/[Oo]ptions specific to device `(.+)'/";
        $allMatched = "/[ ]*([^\s]+) (.+) (\[.+\])/";

        for ($i = 0, $d = -1; $i < count($output); ++$i) {
            if (preg_match($devicePattern, $output[$i], $matched)) {
                // Just parsed the starting section which contains SANE device name
                $scanner = array();
                $name = $matched[1];
                $scanner["name"] = $name;
                $scanner["description"] = $devices[$name];
                $scanners[++$d] = $scanner;
            } else if (preg_match($allMatched, $output[$i], $matched)) { 
                // Parsing options for the current SANE device
                $option = new ScannerOption();
                $key = preg_replace("/^[-]{1,2}/","",$matched[1]);
                $option->name = $matched[1];
                $option->defaultValue = preg_replace("/(\[|\])/","",$matched[3]);

            	if (strstr($matched[2],"|")) { // Fixed set of enumerated values, separated with "|"
            		$option->values = explode("|",$matched[2]);
            		foreach($option->values as $matchedElement){ // Values ranging from low to high, i.e., lo ".." hi overrides enumerated set
            			if(strstr($matched[2],"..")){
                			$option->values = explode("..",$matched[2]);
            				$option->isRange = true;
                        	break;
                        }
                	}
       			} else if (strstr($matched[2],"..")) { // Values ranging from low to high, i.e., lo ".." hi
            		$option->values = explode("..",$matched[2]);
            		$option->isRange = true;
        		} else { // Single element, manually create array
            		$option->values = array($matched[2]);
        		}
            
                // Floor all numerical option values... no reason found to keep them as floats
                if (is_numeric($option->defaultValue)) {
                    $option->defaultValue = floor($option->defaultValue);
                    for ($n = 0; $n < count($option->values); ++$n) $option->values[$n] = floor(floatval($option->values[$n]));
                }
                
                // Check whether interface accepts a range for resolution and enumerate instead
                // This is to avoid having to make a slider for resolution in frontend
                if ($option->isRange && $key == "resolution") {
                    $option->isRange = false;
                    $increasing = array();
                    $decreasing = array();
                    
                    $last = (count($option->values) > 0) ? count($option->values)-1 : 0;
                    $min = $option->values[0];
                    $max = $option->values[$last];
                    $ratio = $max / $min;
                    
                    // Calculate resolution values as follows
                    // (1) Start from lower bound and multiply by increasing integers until reaching upper bound
                    // (2) Start from uppper bound and divide by increasing integers until reaching lower bound
                    for ($res = 1; $res <= $ratio; ++$res) {
                        $increasing[] = $min * $res;
                        $decreasing[] = $max / $res;
                    }
                    
                    // Take the intersection of the two resolution arrays from the above
                    $option->values = array_intersect($increasing, $decreasing);
                    // Ensure that the default value is also part of the list
                    if (!in_array($option->defaultValue,$option->values)) $option->values[] = $option->defaultValue;
                }
                
                sort($option->values);
                $scanners[$d]["options"][$key] = $option;
            } 
        }
        
        return $scanners;
    }
}

?>
