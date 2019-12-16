<?php
namespace App\Models;


class Concept
{
  public $exactMatch = [];
  public $key;
  public $label;
  public $description;
  public $narrower = [];
  // clé gcmd du concept plus large
  public $broader = null;
  public $prefix = null;
  public $root = false;
  public $path = array();
  // les uri de base
  public $scheme = null;
  public static $gcmdscheme = "https://gcmdservices.gsfc.nasa.gov/kms/concept/";
  
  public function __construct($raw, $scheme, $prefix, $type) {
  	$this->scheme = $scheme;
    if (gettype($raw) === 'array' && $type === 'csv2') {
      // from csv "skos play"
      $this->constructFromCSV2($raw, $scheme, $prefix);
    } else if (gettype($raw) === 'array' && $type === 'csv') {
      // from csv type gcmd
      $this->constructFromCSV($raw, $scheme, $prefix);
    } else if ($type === 'rdf') {
      $this->constructFromNode($raw, $scheme, $prefix);
    }

  }
  public function toGcmdRow($size) {
  	$row = array();
  	for($i = 0; $i < $size; $i++) {
  		if (isset($this->path[$i])) {
  			$row[$i] = $this->path[$i];
  		} else {
  			$row[$i] = '';
  		}
  	}
  	array_push($row, $this->key);
  	if ($this->description['en']) {
  		array_push($row, $this->description['en']);
  	}
  	if ($this->label['fr']) {
  		array_push($row, $this->label['fr']);
  	}
  	if ($this->description['fr']) {
  		array_push($row, $this->description['fr']);
  	} else {
  		array_push($row, '');
  	}
  	
  	array_push($row, implode($this->exactMatch));
  	return $row;
  }
  private function constructFromCSV2($raw, $scheme, $prefix) {
    $this->scheme = $scheme;
    $this->key = str_replace($prefix.':', '', $raw['URI']);

    $exactMatchs = preg_split('/,/', $raw['skos:exactMatch']);
    foreach($exactMatchs as $exactMatch) {
      $exactMatch = trim($exactMatch);
      if (!empty($exactMatch)) {
        array_push($this->exactMatch, $exactMatch);
      }
    }
    $this->label = array(
    		'en' => empty(trim($raw['skos:prefLabel@en']))? $raw['skos:prefLabel@fr']:$raw['skos:prefLabel@en'],
    		'fr' => empty(trim($raw['skos:prefLabel@fr']))? $raw['skos:prefLabel@en']:$raw['skos:prefLabel@fr']
    );
    $this->description = array(
        'en' => $raw['skos:definition@en'],
        'fr' => $raw['skos:definition@fr']
    );
    $broader = trim(str_replace($prefix.':', '', $raw['skos:broader']));
    if (!empty($broader)) {
      $this->broader = $broader ;
    }
    $narrowers = preg_split('/,/',$raw['skos:narrower']);
    foreach($narrowers as $narrower) {
      $elt = trim(str_replace($prefix.':', '', $narrower));
      if (!empty($elt)) {
         array_push($this->narrower, $elt);
      }
    }
  }
  private function constructFromCSV($raw, $scheme, $prefix) {
    
  }
  private function constructFromNode($node) {
    $in = $node->firstChild;
    $this->key = $node->getAttribute('rdf:about');
    $this->base =  $node->getAttribute('xml:base');
    while($in = $in->nextSibling) {
      if ($in->nodeType === XML_ELEMENT_NODE) {
        switch($in->tagName) {
          case 'skos:prefLabel':
            $this->registerLabel($in->nodeValue);
            break;
          case 'skos:definition':
            $this->description = $in->nodeValue;
            break;
          case 'skos:broader':
            if (!$root) {
              $this->broader = $in->getAttribute('rdf:resource');
            }
            break;
          case 'skos:narrower':
            array_push($this->narrower, $in->getAttribute('rdf:resource'));
            break;
        }
      }
    }
  }
  public function toRDF($dom) {
    $root = $dom->documentElement;
 
      // concept node
      $node = $dom->createElement('skos:Concept');
      $node->setAttribute('rdf:about',  $this->key);
      $node->setAttribute('xml:base', $this->scheme);
      foreach($this->label as $lang => $label) {
        $labelName = $dom->createElement('skos:prefLabel', $label);
        $labelName->setAttribute('xml:lang', $lang);
        $node->appendChild($labelName);
      }
      foreach($this->description as $lang => $description) {
        $description = $dom->createElement('skos:definition', $description);
        $description->setAttribute('xml:lang', $lang);
        $node->appendChild($description);
      }
      
      if (!is_null($this->broader)) {
        $broad = $dom->createElement('skos:broader');
        $broad->setAttribute('rdf:resource', $this->broader);
        $node->appendChild($broad);
      }
      foreach($this->narrower as $narrower) {
        $narrow = $dom->createElement('skos:narrower');
        $narrow->setAttribute('rdf:resource', $narrower);
        $node->appendChild($narrow);
      }
      foreach($this->exactMatch as $exactMatch) {
        $match = $dom->createElement('skos:exactMatch');
        $match->setAttribute('rdf:resource', $exactMatch);
        $node->appendChild($match);
      }
    return $node;
  }

  public function toRow($prefix) {
    $row = array();
    $row[0] = $prefix .':'. $this->ftkey;
    $row[1] = $this->label;
    $row[2] = '';
    $row[3] = $this->description;
    $row[4] = '';
    if (!is_null($this->broader)) {
      $row[5] = $prefix .':' . $this->ftbroader;
    } else {
      $row[5] = '';
    }
    $narrowers = $this->ftnarrower;
    if (count($narrowers) > 0) {
      array_walk($narrowers,'add_prefix' , $prefix);
          $row[6] = join($narrowers, ', ');
    } else {
      $row[6] = '';
    }
        $row[7] = '';
        $row[8] = self::$gcmdscheme . $this->gcmdkey;
    return $row;
  }

}