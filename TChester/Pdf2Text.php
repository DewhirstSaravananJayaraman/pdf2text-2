<?php
/**
 * Copyright 2009 Thomas Chester
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

interface TChester_iPDFInfo
{
	public function getTitle();
	public function getAuthor();
	public function getSubject();
	public function getKeywords();
	public function getCreator();
	public function getProducer();
	public function getCreationDate();
	public function getModDate();
	public function getContents();
}

interface TChester_iPDFStructure
{
	public function getHeader();
	public function getTrailer();
	public function getBody();
	public function getXref();
}

class TChester_StructureBag
{
	private $_data = array();
	
	public function __construct()
	{
		
	}
	
	public function __set($name, $value)
	{
		$this->_data[$name] = $value;
	}
	
	public function __get($name)
	{
		if (array_key_exists($name, $this->_data)) 
		{
			return $this->_data[$name];
		}
		
		$trace = debug_backtrace();
		trigger_error(
			"Undefined property via __get(): " . $name .
			" in " . $trace[0]['file'] . " on line " .
			$trace[0]['line'],
			E_USER_NOTICE
		);
		
		return null;
	}
	
}


class TChester_Pdf2Text implements TChester_iPDFInfo, TChester_iPDFStructure
{
	private $_title        = "";
	private $_author       = "";
	private $_subject      = "";
	private $_keywords     = "";
	private $_aaplKeywords = "";
	private $_creator      = "";
	private $_producer     = "";
	private $_creationDate = "";
	private $_modDate      = "";
	private $_contents     = "";
	
    private $_bagHeader    = "";
    private $_bagTrailer   = "";
    private $_bagBody      = "";
    private $_bagXref      = "";

    private $_aryObjects   = null;
    private $_aryInfoKeys  = null;

    private $_fileName     = "";
    private $_fileLine     = 0;
    private $_fileBuffer   = "";
	private $_fileHandle   = 0;

    public function __construct($filename)
    {
		ini_set('auto_detect_line_endings', true);

		$this->_bagHeader  = new TChester_StructureBag();
		$this->_bagTrailer = new TChester_StructureBag();
		$this->_bagBody    = new TChester_StructureBag();
		$this->_bagXref    = new TChester_StructureBag();
		
		$this->_bagHeader->type    = "header";
		$this->_bagTrailer->type   = "trailer";
		$this->_bagBody->type      = "body";
		$this->_bagXref->type      = "xref";
		
		$this->_aryObjects   = array();
		$this->_aryInfoKeys  = array();
		
		$this->_bagTrailer->size      = 0;
		$this->_bagTrailer->prev      = 0;
		$this->_bagTrailer->root      = "";
		$this->_bagTrailer->encrypt   = "";
		$this->_bagTrailer->info      = "";
		$this->_bagTrailer->id1       = "";
		$this->_bagTrailer->id2       = "";
		$this->_bagTrailer->startXref = 0;
		$this->_bagTrailer->eof       = "%%EOF";
		
		$patternHeader  = "/^%PDF-(\d+\.\d+)$/";
		$patternObject  = "/^(\d+ \d+) obj\s*(<<.*>>)*(stream)*/";
		$patternTrailer = "/^(trailer)$/";
		$patternXref    = "/^(xref)$/";
	
		$this->_seenHeader  = false;
		$this->_seenTrailer = false;
	
		$this->_fileName   = $filename;
		$this->_fileLine   = 0;
		$this->_fileBuffer = "";
	
		$this->_fileHandle = @fopen($filename, "r");
		if ($this->_fileHandle)
		{
			while (!feof($this->_fileHandle))
			{
				$this->_readLine();
				
				if (1 == preg_match($patternHeader, trim($this->_fileBuffer), $matches) && !$this->_seenHeader)
					$this->_processPDFHeader($matches);
				
				if (1 == preg_match($patternObject, trim($this->_fileBuffer), $matches))	
					$this->_processPDFBody($matches);
				
				if (1 == preg_match($patternXref, trim($this->_fileBuffer), $matches))	
					$this->_processPDFXref($matches);

				if (1 == preg_match($patternTrailer, trim($this->_fileBuffer), $matches) && !$this->_seenTrailer)
					$this->_processPDFTrailer($matches);
				
			}
			fclose($this->_fileHandle);
			
			$this->_processPDFInfoBlock();
			$this->_processContents();
		}
    } 

	/* Interface: iPDFInfo */
	public function getTitle()        { return $this->_title;        }
	public function getAuthor()       { return $this->_author;       }
	public function getSubject()      { return $this->_subject;      }
	public function getKeywords()     { return $this->_keywords;     }
	public function getCreator()      { return $this->_creator;      }
	public function getProducer()     { return $this->_producer;     }
	public function getCreationDate() { return $this->_creationDate; }
	public function getModDate()      { return $this->_modDate;      }
	public function getContents()     { return $this->_contents;     }
    
	/* Interface: iPDFStructure */
	public function getHeader()       { return $this->_bagHeader;    }
	public function getTrailer()      { return $this->_bagTrailer;   }
	public function getBody()         { return $this->_bagBody;      }
	public function getXref()         { return $this->_bagXref;      }

    private function _processPDFHeader($matches)
    {
		//echo "\nPDF Header\n";
		$this->_bagHeader->header  = $matches[0];
		$this->_bagHeader->version = $matches[1];
		$this->_seenHeader = true;
	}

	private function _processPDFBody($matches)
	{
		//echo "\nPDF Body\n";

	 	$key          = $matches[1];
		$dictionary   = "";
		$stream       = "";
		$contents     = "";
		$probableText = false;
		
		$contents = $this->_readToEndOfBlock("/^endobj$/");
		
		//echo "contents:: " . htmlentities($contents) . "\n";
		
		$startIdx = strpos($contents, "<<", 0);
		$stopIdx  = strpos($contents, ">>", $startIdx) + 2;
		
		if ($startIdx !== false && $stopIdx !== false)
			$dictionary = substr($contents, $startIdx, $stopIdx - $startIdx);
		
		$startIdx = strpos($contents, "stream", 0) + strlen("stream");
		$stopIdx  = strpos($contents, "endstream", 0);
		
		if ($startIdx !== false && $stopIdx !== false)
			$stream = substr($contents, $startIdx, $stopIdx - $startIdx);
		
		if ($stream != "")
		{
			$contents   = $this->_getStreamData($dictionary, $stream);

			// This heuristic assumes that if the decoded contents are regular
			// readable text then within the first 26 characters we would expect
			// to see at least one space character.
			if ($contents != "" && strpos(substr($contents, 0, 26), " ") !== false)
			  $probableText = true;
			else
			  $probableText = false;
		}
		else
		{
		      if (strpos($dictionary, "/Device"  , 0) === false &&
	        	    strpos($dictionary, "/Image"   , 0) === false &&
	        	    strpos($dictionary, "/Metadata", 0) === false)
			{
			  $contents   = $this->_getStreamEmbeddedData(substr($contents, strlen($dictionary)), false);
			  $probableText = true;
                  }
		}
		
		$this->_aryObjects[] = array(
			"key" 		 	=> $key, 
			"dictionary" 	=> $dictionary, 
			"stream" 		=> $stream,
			"contents" 		=> $contents,
			"probableText" 	=> $probableText
		);
	
		$this->_bagBody->objects = $this->_aryObjects;
	}

	private function _processPDFXref($matches)
	{
		//echo "\nPDF Xref\n";
	}

	private function _processPDFTrailer($matches)
	{
		//echo "\nPDF Trailer\n";
		
		$contents = $this->_readToEndOfBlock("/^(%%EOF)$/");
		
		$startIdx = strpos($contents, "<<", 0);
		$stopIdx  = strpos($contents, ">>", 0) + strlen(">>");
		
		$this->_bagTrailer->dictionary = substr($contents, $startIdx, $stopIdx - $startIdx);
	
		$patternId     = "/\/ID\s{0,1}\[\s{0,1}<(\d|\w+)>\s{0,1}<(\d|\w+)>\s{0,1}\]/";
	
		if (1 == preg_match($patternId, $this->_bagTrailer->dictionary, $matches))
		{
			$this->_bagTrailer->id1 = $matches[1];
			$this->_bagTrailer->id2 = $matches[2];
		}
	
	    $patternRoot   = "/\/Root\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
	
	    if (1 == preg_match($patternRoot, $this->_bagTrailer->dictionary, $matches))
			$this->_bagTrailer->root = $matches[1];
	
	    $patternInfo   = "/\/Info\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
	
	    if (1 == preg_match($patternInfo, $this->_bagTrailer->dictionary, $matches))
			$this->_bagTrailer->info = $matches[1];
		
		$patternSize   = "/\/Size\s{0,1}(\d+)\s{0,1}/";
		
	    if (1 == preg_match($patternSize, $this->_bagTrailer->dictionary, $matches))
			$this->_bagTrailer->size = $matches[1];

		$patternPrev   = "/\/Prev\s{0,1}(\d+)\s{0,1}/";
		
	    if (1 == preg_match($patternPrev, $this->_bagTrailer->dictionary, $matches))
			$this->_bagTrailer->prev = $matches[1];

		$patternStartXref = "/startxref\s*(\d+)\s*%%EOF/";
		
		if (1 == preg_match($patternStartXref, $contents, $matches))
			$this->_bagTrailer->startXref = $matches[1];
		
		$this->_seenTrailer = true;
	}

	private function _processPDFInfoBlock()
	{
		$info = $this->_bagTrailer->info;
		
		if ($info == "")
			return;
		
		$data = $this->_getContentBlockById($info, false);
		
		$data = str_replace("\(", "[", $data);
		$data = str_replace("\)", "]", $data);
				
		$patternTitle = "/\/Title\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternTitle, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_title = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternTitle = "/\/Title\(([^)]+)\)/";
			if (1 == preg_match($patternTitle, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_title = $matches[1];
			}
		}
			
		$patternAuthor = "/\/Author\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternAuthor, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_author = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternAuthor = "/\/Author\(([^)]+)\)/";
			if (1 == preg_match($patternAuthor, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_author = $matches[1];
			}			
		}
		
		$patternSubject = "/\/Subject\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternSubject, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_subject = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternSubject = "/\/Subject\(([^)]+)\)/";
			if (1 == preg_match($patternSubject, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_subject = $matches[1];
			}
		}
		
		$patternProducer = "/\/Producer\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternProducer, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_producer = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternProducer = "/\/Producer\(([^)]+)\)/";
			if (1 == preg_match($patternProducer, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_producer = $matches[1];
			}
		}
		
		$patternCreator = "/\/Creator\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternCreator, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_creator = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternCreator = "/\/Creator\(([^)]+)\)/";
			if (1 == preg_match($patternCreator, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_creator = $matches[1];
			}
		}
		
		$patternCreationDate = "/\/CreationDate\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternCreationDate, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_creationDate = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternCreationDate = "/\/CreationDate\(([^)]+)\)/";
			if (1 == preg_match($patternCreationDate, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_creationDate = $matches[1];
			}
		}
		
		$patternModDate = "/\/ModDate\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternModDate, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_modDate = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternModDate = "/\/ModDate\(([^)]+)\)/";
			if (1 == preg_match($patternModDate, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_modDate = $matches[1];
			}
		}
		
		$patternKeywords = "/\/Keywords\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternKeywords, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_keywords = $this->_getContentBlockById($matches[1], true);
		}
		else
		{
			$patternKeywords = "/\/Keywords\(([^)]+)\)/";
			if (1 == preg_match($patternKeywords, $data, $matches))
			{
				$this->_aryInfoKeys[] = $info;
				$this->_keywords = $matches[1];
			}
		}

		$patternAaplKeywords = "/\/AAPL\:Keywords\s{0,1}(\d+\s{0,1}\d+)\s{0,1}R/";
		
		if (1 == preg_match($patternAaplKeywords, $data, $matches))
		{
			$this->_aryInfoKeys[] = $matches[1];
			$this->_aaplKeywords = $this->_getContentBlockById($matches[1], true);
		}

	}

	private function _processContents()
	{
		$contents  = "";
		foreach ($this->_bagBody->objects as $obj)
		{
			$isInfoKey = false;
			foreach ($this->_aryInfoKeys as $infoKey)
				if ($infoKey == $obj['key'])
					$isInfoKey = true;
					
			if ($obj['probableText'] == true && !$isInfoKey)
				if ($contents == "")
					$contents = trim($obj['contents']);
				else
					$contents .= " " . trim($obj['contents']);
		}		
		$this->_contents = $contents;
	}

	private function _getContentBlockById($id, $wantContent)
	{
		foreach ($this->_bagBody->objects as $obj)
			if ($obj['key'] == $id)
				if ($wantContent)
					return $obj['contents'];
				else
					return $obj['dictionary'];
		return "";
	}

	private function _getStreamData($header, $data)
	{
	    if (strpos($header, "/Device"  , 0) !== false ||
	        strpos($header, "/Image"   , 0) !== false ||
	        strpos($header, "/Metadata", 0) !== false)
	      return false;

	    if (strpos($header, "/FlateDecode", 0) === false)
	    {
	        // Stream is plain text
	        return $this->_getStreamEmbeddedData($data, false);
	    }
	    else
	    {
	        // Stream encoded with zlib compress function === FlateDecode
	        $offset = 0;

	        // Windows line separator encoding
	        if (substr($data, 0, 1) == "\r" && substr($data, 1, 1) == "\n")
	            $offset = 2;

	        // UNIX line separator encoding
	        if (substr($data, 0, 1) == "\n")
	            $offset = 1;

		$contents = gzuncompress(substr($data, $offset, strlen($data) - ($offset * 2)));

		//echo "\n\nUncompressed Contents: " . htmlentities($contents) . "\n\n";

		return $this->_getStreamEmbeddedData($contents, true);
	    }
	}

	private function _getStreamEmbeddedData($data, $wasCompressed = true)
	{
	    $char      = "";
	    $paren     = false;
	    $results   = "";
	    $tjFollows = false;

		$data = str_replace("\(", "[", $data);
		$data = str_replace("\)", "]", $data);

	    for ($i = 0; $i < strlen($data); $i++)
	    {
			$char = substr($data, $i, 1);

			if ($char == "(") 
			{
				$paren     = true;
				$tjFollows = false;
			}

	        if ($char == ")") 
			{
				$paren = false;
				
				if ("Tj" == substr($data, $i+1, 2))
					$tjFollows = true;
			}

	        if ($char == ")" && (!$wasCompressed || $tjFollows)) $results .= " ";
	        
			//if ($char == ")" && $tjFollows == true) $results .= "\n";

	        if ($paren == 1 && $char != "(" && $char != ")") $results .= $char;
	    }

	    return $results;
	}
	

	private function _readToEndOfBlock($patternStop)
	{
		$buffer = "";

		do
		{
			$buffer .= $this->_fileBuffer;
			
			if (1 == preg_match($patternStop, trim($this->_fileBuffer), $matches))
				break;
			
		} while ($this->_readLine());
		
		return $buffer;
		
	}

	private function _readLine()
	{
		if (!feof($this->_fileHandle))
		{
			$this->_fileBuffer = fgets($this->_fileHandle);
			$this->_fileLine++;
			//echo htmlentities($this->_fileBuffer);
			return 1;
		}
		
		return 0;
	}
	
}

?>
