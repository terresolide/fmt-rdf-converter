<?php
namespace App\Models;


class Concept
{
	public $gcmdkey;
	public $key;
	public $ftkey = null;
	public $label;
	public $description;
	// tableau des clés gcmd des concepts plus fins 
	public $narrower = [];
	public $ftnarrower = [];
	// clé gcmd du concept plus large
	public $broader = null;
	public $ftbroader = null;
	public $prefix = null;
	public $root = null;
	// les uri de base
	public static $scheme = "https://w3id.org/formater/variable/";
	public static $gcmdscheme = "https://gcmdservices.gsfc.nasa.gov/kms/concept/";
	
	public function __construct($node, $root = false) {
		$in = $node->firstChild;
		$this->gcmdkey = $node->getAttribute('rdf:about');
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
	public function registerLabel ($label) {
		$min = strtolower($label);
		$this->key = preg_replace('/\s+/', '_', $min);
		$this->key = preg_replace('/\//', '-', $this->key);
		$label = ucfirst($min);
		$label = str_replace(' earth', ' Earth', $label);
		$this->label = $label;
		
	}
	public function toRdf($dom) {
		$root = $dom->documentElement;
		if( is_null($this->narrower)) {
// 			// conceptScheme node ou Solid Earth node (à voir)
// 			$node = $dom->createElement('skos:ConceptScheme');
// 			$node->setAttribute('rdf:about', self::scheme);
// 			$labelName = $dom->createElement('skos:prefLabel', 'ForM@Ter | Solid Earth variable ontology');
// 			$labelName->setAttribute('xml:lang', 'en');
// 			$node->appendChild($labelName);
// 			$description = $dom->createElement('skos:description', 'Ontology of Measured properties, quantities calculed as part of French Solid Earth Hub');
// 			$description->setAttribute('xml:lang', 'en');
// 			$node->appendChild($description);
		} 
			// concept node
			$node = $dom->createElement('skos:Concept');
			$node->setAttribute('rdf:about', self::$scheme .  $this->ftkey);
			$labelName = $dom->createElement('skos:prefLabel', $this->label);
			$labelName->setAttribute('xml:lang', 'en');
			$node->appendChild($labelName);
			$description = $dom->createElement('skos:definition', $this->description);
			$description->setAttribute('xml:lang', 'en');
			$node->appendChild($description);
			if (!is_null($this->ftbroader)) {
				$broad = $dom->createElement('skos:broader');
				$broad->setAttribute('rdf:resource', self::$scheme . $this->ftbroader);
				$node->appendChild($broad);
			}
			foreach($this->ftnarrower as $narrower) {
				$narrow = $dom->createElement('skos:narrower');
				$narrow->setAttribute('rdf:resource', self::$scheme . $narrower);
				$node->appendChild($narrow);
			}
			$match = $dom->createElement('skos:exactMatch');
			$match->setAttribute('rdf:resource', self::$gcmdscheme .$this->gcmdkey);
			$node->appendChild($match);
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