<?php

namespace App\Controllers;

use \Core\View;
use \EasyRdf\Graph as RdfGraph;
use \App\Models\Concept;
/**
 * Home controller
 *
 * PHP version 7.0
 */
class Test extends \Core\Controller
{
    public $concepts = array();
    public $outconcepts = array();
    public $sheets = array();
    public $keys = array();
    public $double = array();
    public static $root = '2b9ad978-d986-4d63-b477-0f5efc8ace72';
    public static $prefix = array(
      "earth_gases-liquids" => "non_solid",
      "geochemistry" => "geochemistry",
      "geodetics" => "geodetics",
      "geomagnetism" => "geomagnetism",
      "geomorphic_landforms-processes" => "geomorphic",
      "geothermal_dynamics" => "geothermal",
      "gravity-gravitational_field" => "gravity",
      "rocks-minerals-crystals" => "solid",
      "tectonics" => "tectonics"
    );
    public static $row = array(
      "URI", 
      "skos:prefLabel@en", 
      "skos:prefLabel@fr", 
      "skos:definition@en",
      "skos:definition@fr", 
      "skos:broader", 
      "skos:narrower", 
      "skos:related", "skos:exactMatch");
    /**
     * Show the index page
     *
     * @return void
     */
    public function indexAction()
    {
        View::renderTemplate('Test/index.html');
    }
    
    public function gcmdAction() {
    	
        // fichier d'entrée GCMD
		$domin = new \DOMDocument();
		$domin->load("http://rdf.test/data/sciencekeywords.rdf");
		$rootin = $domin->documentElement;
			
		// on part du noeud solid earth 2b9ad978-d986-4d63-b477-0f5efc8ace72
		$xpath = new \DOMXPath($domin);
		$xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
		$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
	    $this->loadConcepts(self::$root, $xpath, true);
	    $this->changeKey();
	    $this->completeConcepts($this->sheets);
	    $this->updateBroader();
	    foreach(self::$prefix as $key) {
		    $filename = UPLOAD_DIR .'/'.$key.'.xls';
		    $this->createCSV($key, $filename);
	    }
	    
// RDF output
// -----------
// 	    $domout = $this->initRdfDocument();
// 	    $root = $domout->documentElement;
// 	    header("Content-Type:text/xml");
// 		 echo $domout->saveXML();
		
    }
    public function toArray($key) {
    	
    }
    public function createCSV($key, $filename) {
    	$fp = fopen($filename, 'w');
    	$this->headerCSV($fp);
    	fputcsv($fp, self::$row);
    	$rows = array();
    	$rows = $this->prepareRows($rows, $key);
    	var_dump($rows);
    	foreach($rows as $row) {
    		fputcsv($fp, $row);
    	}
    	fclose($fp);
    }
    public function prepareRows($rows, $key) {
    	$concept = $this->outconcepts[$key];
    	array_push($rows, $concept->toRow('ft'));
    	foreach($concept->ftnarrower as $key2) {
    		$rows = $this->prepareRows($rows, $key2);
    	}
    	return $rows;
    }
    public function headerCSV($fp) {
    	fputcsv($fp, array('ConceptScheme URI', Concept::$scheme));
    	fputcsv($fp, array('PREFIX', 'ft', Concept::$scheme));
    	fputcsv($fp, array(''));
    	fputcsv($fp, array('Ce fichier est utilisé pour enrichir le thésaurus des mots scientifiques du gcmd'));
    	fputcsv($fp, array(''));
    	fputcsv($fp, array('La traduction du label et de la définition de chaque concept serait un + (les colonnes skos:prefLabel@fr et skos:definition@fr)'));
    	fputcsv($fp, array('la colonne skos:exactMatch est utilisé pour créer un lien avec les concepts d\'autres thésaurus'));
    	fputcsv($fp, array('   - l\'URI du terme correspondant du gcmd s\'y trouve déjà'));
    	fputcsv($fp, array('   - pour ajouter des concepts correspondants d\'autres thésaurus, il faut séparer les URI par des virgules ","'));
    	fputcsv($fp, array(''));
    	fputcsv($fp, array('Les identifiants de chaque concept ont été générés automatiquement et seront modifiés ultérieurement'));
    	fputcsv($fp, array(' Ils ne doivent pas être modifiés, ils sont en effet utilisés pour créer des liens entre concepts'));
    	fputcsv($fp, array(''));
    	fputcsv($fp, array('Les différents liens'));
    	fputcsv($fp, array('  skos:broader : Il s\'agit du concept plus large (ou concept parent), il est unique'));
    	fputcsv($fp, array('  skos:narrower : les concepts plus fins (ou concepts enfants)'));
    	fputcsv($fp, array('  ...@todo'));
    	fputcsv($fp, array(''));
    	fputcsv($fp, array(''));
    }
    public function loadConcepts($key, $xpath, $begin = false) {
    	$node = $xpath->query('//skos:Concept[@rdf:about="'.$key.'"]')[0];
    	$concept = new Concept($node, $begin);
    	$this->concepts[$concept->gcmdkey] = $concept;
    	if (count($concept->narrower) === 0) {
    		array_push($this->sheets, $concept->gcmdkey);
    	}
    	foreach($concept->narrower as $gcmdkey) {
    		$this->loadConcepts($gcmdkey, $xpath);
    	}
    }
    public function completeConcepts($sheets) {
    	// crée les clés pour nos concepts et verification que pas de doublon!!
    	// on part des concepts enfant qui n'ont qu'un broader

    	foreach($sheets as $sheetnum) {
    		$concept = $this->concepts[$sheetnum];

    		$key = $this->buildKey($sheetnum);
    		$gcmd = $concept->gcmdkey;
    		$this->concepts[$sheetnum]->ftkey = $key;
    		$this->outconcepts[$key] = $concept;
    		$this->outconcepts[$key]->ftkey = $key;
    		
    		$broader_key = $this->concepts[$sheetnum]->broader;
    		
    		if (!is_null($broader_key) && !array_key_exists($this->concepts[$broader_key]->ftkey, $this->outconcepts)) {
    			$this->completeConcepts([$broader_key]);
    		}
    	}
    	
    	
    }
    public function buildKey($gcmd) {
    	$concept = $this->concepts[$gcmd];
    	$key = $concept->key;
    	$broader = $concept->broader;
    	if (is_null($broader)) {
    		return $key;
    	}
    	$keys = array($key);
    	while ($broader !== self::$root) {
    		// $key = $this->concepts[$broader]->key .'-' .$key;
    		array_unshift($keys,  $this->concepts[$broader]->key);
    		$broader = $this->concepts[$broader]->broader;
    	}
    	$key = join($keys, '-') ;
    	return $key;
    }
    public function updateBroader () {
    	foreach($this->outconcepts as $key => $outconcept) {
    		$concept = $this->concepts[$outconcept->gcmdkey];
	    	if(!is_null($concept->broader)){
	    		$broader_key = $this->concepts[$concept->broader]->ftkey;
	    		$this->outconcepts[$key]->ftbroader = $broader_key;
	    	}
	    	$this->outconcepts[$key]->ftnarrower = [];
	    	foreach($concept->narrower as $narrower) {
	    		array_push($this->outconcepts[$key]->ftnarrower , $this->concepts[$narrower]->ftkey);
	    	}
    	}
    }
   
    public function changeKey() {
    	foreach($this->concepts as $gcmd => $concept) {
    		if ($concept->broader === self::$root) {
    			$this->concepts[$gcmd]->key = self::$prefix[$concept->key];
    		}
    	}
    }
    public function searchPrefix ($gcmd) {
    	if ($this->concepts[$gcmd]->broader === self::$root) {
    	    return self::$prefix[$this->concepts[$gcmd]->key];
    	} else if (is_null($this->concepts[$gcmd]->broader)) {
    		return null;
    	} else {
    		return $this->searchPrefix($this->concepts[$this->concepts[$gcmd]->broader]->gcmdkey);
    	}
    }
    public function addPrefix() {
    	$keys = array_keys($this->outconcepts);
    	foreach($keys as $key) {
    		
    		
    	}
    }
    public function xmlAction() {
    	$file = "http://rdf.test/data/cf-standard-name-table.rdf";
    	$dom = new \DOMDocument();
    	$dom->load($file);
    	
    	
    	$domtree = $this->initRdfDocument();
    	$scheme = 'http://mmisw.org/ont/cf/parameter';
    	// element parameter racine du skos
    	$xpath = new \DOMXPath($dom);
    	$xpath->registerNamespace('owl', 'http://www.w3.org/2002/07/owl');
    	$node = $xpath->query('//owl:Ontology');
    	$name = $xpath->query('//owl:Ontology/omv:name');
    	$description = $xpath->query('//owl:Ontology/omv:description');
      // var_dump($name->item(0)->nodeValue);
    	$outroot = $domtree->documentElement;
    	$skosroot = $domtree->createElement('skos:ConceptScheme');
    	$skosroot->setAttribute('rdf:about', $scheme);
    	$labelName = $domtree->createElement('skos:prefLabel');
    	$labelName->appendChild($domtree->createTextNode($name->item(0)->nodeValue));
    	$skosroot->appendChild($labelName);
    	$skosDescription = $domtree->createElement('skos:description');
    	$skosDescription->appendChild($domtree->createTextNode($description->item(0)->nodeValue));
    	$skosroot->appendChild($skosDescription);
    	$outroot->appendChild($skosroot);
    	
    	// liste des concepts (Standard_Name)
    	$root=$dom->documentElement;
    	$nodes = $root->getElementsByTagName("Standard_Name");
    	foreach ($nodes as $node) {
    		if ($node->getAttribute('rdf:about') !== 'parameter') {
	    		$new = $domtree->createElement('skos:hasTopConcept');
	    		// var_dump($node->getAttribute('rdf:about'));
	    		$new->setAttribute('rdf:resource', $scheme . '/' . $node->getAttribute('rdf:about'));
	    		$skosroot->appendChild($new);
	    		$concept = $domtree->createElement('skos:Concept');
	    		$concept->setAttribute('rdf:about', $scheme . '/' . $node->getAttribute('rdf:about'));
	    		$labelName = $domtree->createElement('skos:prefLabel');
	    		$labelName->setAttribute('xml:lang', 'en');
	    		$labelName->appendChild($domtree->createTextNode($node->getAttribute('rdf:about')));
	    		$concept->appendChild($labelName);
	    		
	    		// import definition and unit
	    		$definition = $node->firstChild->nextSibling;
	    		
	    		$unit = $node->firstChild->nextSibling->nextSibling->nextSibling;
	    		$node = $domtree->importNode($definition, true);
	    		$node->setAttribute('xml:lang', 'en');
	    		
	    		if(!is_null($unit)) {
	    			$node->appendChild($domtree->createTextNode(' ( UNIT: ' . $unit->nodeValue . ' )'));
	    			$concept->appendChild($node);
	    			$concept->appendChild($domtree->importNode($unit, true));
	    		} else {
	    			$concept->appendChild($node);
	    		}
	    		// $unit = $node->firstChild->nextSibling->nextSibling->nextSibling->nodeValue;
	    		// $skosDefinition = $domtree->createElement('skos:definition');
	    		//var_dump($unit);
	    		
	    		//var_dump($definition);
	    		$outroot->appendChild($concept);
    		}
    	}
   
        header("Content-Type:text/xml");
        echo $domtree->saveXML();
    }
    private function initRdfDocument() {
    	$domtree = new \DOMDocument('1.0', 'UTF-8');
    	$xmlRoot = $domtree->createElement('rdf:RDF');
    	$domtree->appendChild($xmlRoot);
    	// $rdfNode = $domtree->createElement('rdf:RDF');
    	$xmlRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    	$xmlRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:skos', 'http://www.w3.org/2004/02/skos/core#');
    	// $domtree->appendChild($rdfNode);
    	
    	return $domtree;
    }
    private function createConceptSchemeNode($dom) {
    	// conceptScheme node ou Solid Earth node (à voir)
    	$node = $dom->createElement('skos:ConceptScheme');
    	$node->setAttribute('rdf:about', $node);
    	$labelName = $dom->createElement('skos:prefLabel', 'ForM@Ter | Solid Earth variable ontology');
    	$root->appendChild($node);
    	$labelName->setAttribute('xml:lang', 'en');
    	$node->appendChild($labelName);
    	$description = $dom->createElement('skos:description', 'Ontology of Measured properties, quantities calculed as part of French Solid Earth Hub');
    	$description->setAttribute('xml:lang', 'en');
    	$node->appendChild($description);
    	return $node;
    }
}
