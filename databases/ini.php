<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.1 Service Release
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://phpbots.sf.net/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > ini-file database module
|   > Module written by Manick
|   > Module Version Number: 2.2.1 alpha
+---------------------------------------------------------------------------
|   > This program is free software; you can redistribute it and/or
|   > modify it under the terms of the GNU General Public License
|   > as published by the Free Software Foundation; either version 2
|   > of the License, or (at your option) any later version.
|   >
|   > This program is distributed in the hope that it will be useful,
|   > but WITHOUT ANY WARRANTY; without even the implied warranty of
|   > MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
|   > GNU General Public License for more details.
|   >
|   > You should have received a copy of the GNU General Public License
|   > along with this program; if not, write to the Free Software
|   > Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
+---------------------------------------------------------------------------
|   Changes
|   =======-------
|   > If you wish to suggest or submit an update/change to the source
|   > code, email me at manick@manekian.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

class ini {

	private $filename;
	private $error;
	private $ini = array();
	private $numSections;
	
	//used in isMatched()
	private $search;
	private $searchParts;


	//Load ini into memory
	public function __construct($filename)
	{
		$this->error = false;
		$this->filename = $filename;

		$filePtr = @fopen($filename, "r");

		if ($filePtr === false)
		{
			$filePtr = @fopen($filename, "a");

			if ($filePtr === false)
			{
				$this->error = true;
				return;
			}
			else
			{
				fclose($filePtr);
				return;
			}
		}

		$fileData = "";

		while (!feof($filePtr))
		{
			$fileData .= fread($filePtr, 4096);
		}

		fclose($filePtr);

		$fileData = str_replace("\r", "", $fileData);

		$lines = explode("\n", $fileData);

		$currSection = "";
		$this->numSections = 0;

		foreach($lines AS $line)
		{
			$line = trim($line);

			$offsetA = strpos($line, "[");
			$offsetB = strpos($line, "]");

			if ($offsetA === 0)
			{
				$currSection = substr($line, 1, $offsetB - 1);
				$this->numSections++;
				$this->ini[$currSection] = array();
			}
			else
			{
				if ($currSection != "")
				{
					$offsetC = strpos($line, "=");

					if ($offsetC !== false)
					{
						$var = trim(substr($line, 0, $offsetC));
						$val = substr($line, $offsetC + 1);

						if ($var != "")
						{
							$this->ini[$currSection][$var] = $val;
						}
					}
					else
					{
						$this->ini[$currSection][$line] = true;
					}
				}
			}
		}
	}

	public function getError()
	{
		return $this->error;
	}
	
	public function getSections()
	{
		$sections = array();

		if ($this->numSections == 0)
		{
			return $sections;
		}

		foreach ($this->ini AS $section => $vals)
		{
			$sections[] = $section;
		}
		
		return $sections;
	}

	public function getVars($section)
	{
		if (!isset($this->ini[$section]))
		{
			return false;
		}
		
		return $this->ini[$section];
	}
	
	public function sectionExists($section)
	{
		if (isset($this->ini[$section]) && is_array($this->ini[$section]))
		{
			return true;
		}
		return false;
	}

	public function getSection($section)
	{
		return $this->getVars($section);
	}

	public function randomSection($num = 1)
	{
		if ($this->numSections == 0 || $num < 1 || $num > $this->numSections)
		{
			return false;
		}

		return array_rand($this->ini, $num);
	}

	public function randomVar($section, $num = 1)
	{
		if (!isset($this->ini[$section]))
		{
			return false;
		}

		$count = count($this->ini[$section]);

		if ($count == 0 || $num < 1 || $num > $count)
		{
			return false;
		}

		return array_rand($this->ini[$section], $num);
	}


	public function searchSections($search, $type = EXACT_MATCH)
	{
		$results = array();

		if (trim($search) == "")
		{
			return $results;
		}

		if ($this->numSections == 0)
		{
			return;
		}

		foreach($this->ini AS $section => $vars)
		{
			if ($this->isMatched($search, $section, $type))
			{
				$results[] = $section;
			}
		}

		return $results;
	}

	public function searchVars($section, $search, $type = EXACT_MATCH)
	{
		$results = array();

		if (trim($search) == "")
		{
			return $results;
		}

		if (!isset($this->ini[$section]))
		{
			return $results;
		}

		if ($this->numSections == 0)
		{
			return;
		}

		if (count($this->ini[$section]) == 0)
		{
			return $results;
		}

		foreach($this->ini[$section] AS $var => $val)
		{
			if ($this->isMatched($search, $var, $type))
			{
				$results[] = $var;
			}
		}

		return $results;
	}

	public function searchSectionsByVar($var, $search, $type = EXACT_MATCH)
	{
		$results = array();

		if ($this->numSections == 0)
		{
			return $results;
		}

		foreach($this->ini AS $section => $vars)
		{
			if (isset($vars[$var]))
			{
				if ($this->isMatched($search, $vars[$var], $type))
				{
					$results[] = $section;
				}
			}
		}
		
		return $results;

	}

	public function searchVals($section, $search, $type = EXACT_MATCH)
	{
		$results = array();

		if (trim($search) == "")
		{
			return $results;
		}

		if (!isset($this->ini[$section]))
		{
			return $results;
		}

		if ($this->numSections == 0)
		{
			return;
		}

		if (count($this->ini[$section]) == 0)
		{
			return $results;
		}

		foreach($this->ini[$section] AS $var => $val)
		{
			if ($this->isMatched($search, $val, $type))
			{
				$results[] = $var;
			}
		}

		return $results;
	}
	
	private function isMatched(&$needle, &$haystack, $type = EXACT_MATCH)
	{

		if ($type == EXACT_MATCH)
		{
			if ($haystack == $needle)
			{
				return true;
			}
		}
		
		if ($type == CONTAINS_MATCH)
		{
			if (strpos(strtolower($haystack), strtolower($needle)) !== false)
			{
				return true;
			}
		}

		if ($search != $this->search)
		{
			$this->searchParts = explode(chr(32), $search);
			$this->search = $search;
		}

		if ($type == AND_MATCH)
		{
			$foundAll = true;
	
			foreach($this->searchParts AS $part)
			{
				if (strpos($val, $part) === false)
				{
					$foundAll = false;
					break;
				}
			}
	
			if ($foundAll == true)
			{
				return true;
			}
		}
		else if ($type == OR_MATCH)
		{
			foreach($this->searchParts AS $part)
			{
				if (strpos($val, $part) !== false)
				{
					return true;
					break;
				}
			}
		}

		return false;
	}

	
	public function deleteSection($section)
	{
		if (isset($this->ini[$section]))
		{
			unset($this->ini[$section]);
			return true;
		}

		return false;
	}

	public function deleteVar($section, $var)
	{
		if (isset($this->ini[$section]))
		{
			if (isset($this->ini[$section][$var]))
			{
				unset($this->ini[$section][$var]);
				return true;
			}
		}

		return false;
	}

	public function numSections()
	{
		return $this->numSections;
	}
	
	public function numVars($section)
	{
		if (isset($this->ini[$section]))
		{
			return count($this->ini[$section]);
		}
		return 0;
	}

	public function setIniVal($section, $var, $val)
	{
		if ($this->error == true)
		{
			return;
		}

		if (!isset($this->ini[$section]))
		{
			$this->numSections++;
			$this->ini[$section] = array();
		}

		if (strpos($var, "=") !== false)
		{
			return false;
		}

		$this->ini[$section][$var] = $val;

		return true;
	}

	public function getIniVal($section, $var)
	{
		if ($this->error == true)
		{
			return;
		}

		if (isset($this->ini[$section])
				 && isset($this->ini[$section][$var]))
		{
			return $this->ini[$section][$var];
		}
		else
		{
			return false;
		}
	}

	//Update and write ini to file
	public function writeIni()
	{
		if ($this->error == true)
		{
			return;
		}
		
		if ($this->numSections == 0)
		{
			return;
		}

		$output = "";

		foreach ($this->ini AS $section => $vars)
		{

			$output .= "[" . $section . "]\n";

			if (count($vars))
			{
				foreach ($vars AS $var => $val)
				{
					$output .= $var . "=" . $val . "\n";
				}
			}
		}

		$filePtr = fopen($this->filename, "at");

		if ($filePtr === false)
		{
			$this->error = true;
			return false;
		}
		
		flock($filePtr, LOCK_EX);

		ftruncate($filePtr, 0);

		if (fwrite($filePtr, $output) === FALSE)
		{
			$this->error = true;
		}

		flock($filePtr, LOCK_UN);

		fclose($filePtr);

		return !$this->error;
	}

}

?>
