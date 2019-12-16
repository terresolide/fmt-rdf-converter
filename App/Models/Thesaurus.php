<?php
namespace App\Models;
use App\Models\Concept;
/**
 * Class Thesaurus
 *
 * PHP version 7.2
 */
class Thesaurus
{
    public $scheme = '';
    public $prefix = '';
    public $concepts = array();
    public $roots = [];
    public $sheets = [];
    public $title = '';
    public $description = '';
    public $length = 0;
    
    public static $skosRow = array(
      "URI", 
      "skos:prefLabel@en", 
      "skos:prefLabel@fr", 
      "skos:definition@en",
      "skos:definition@fr", 
      "skos:broader", 
      "skos:narrower", 
      "skos:related", 
      "skos:exactMatch");

    
    public static $type = array(
      'csv' => 'csv gcmd',
      'csv2' => 'csv skos',
      'rdf' => 'rdf'
    );
    public function __construct($file, $options) {
      if ($options['thesaurusName'] && !empty($options['thesaurusName'])) {
        $this->title = filter_var($options['thesaurusName'],FILTER_SANITIZE_STRING);
      }
      if ($options['thesaurusDescription'] && !empty($options['thesaurusDescription'])) {
        $this->description = filter_var($options['thesaurusDescription'],FILTER_SANITIZE_STRING);
      }
        switch ($options['type']) {
          case 'csv':
          case 'csv2':
            $this->loadFromCSV($file, $options['type']);
            break;
          case 'rdf':
            $this->loadFromRdf($file);
            break;
        }
    }
    public function toRDF () {
      $dom = $this->initRdfDocument();
      $root = $dom->documentElement;
      foreach($this->concepts as $concept) {
        $node = $concept->toRDF($dom);
        $root->appendChild($node);
      }
      return $dom;
    }
    public function toGCMD () {
    	$out = fopen('php://output', 'w');
    	fputcsv($out, array('Scheme:'. $this->scheme));
    	$head = array();
    	for($i = 0; $i < $this->length; $i++) {
    		array_push($head, 'level_' . ($i + 1));
    	}
    	array_push($head, 'UUID');
    	array_push($head, 'description@en');
    	array_push($head, 'label@fr');
    	array_push($head, 'description@fr');
    	array_push($head, 'Match');
    	fputcsv($out, $head);
    	foreach($this->concepts as $concept) {
    		fputcsv($out, $concept->toGcmdRow($this->length));
    	}
    	fclose($out);
    }
    private function loadFromCSV($files, $type) {
      foreach ($files['tmp_name'] as $file) {
        $this->loadCSVFile($file);
      }
      $this->fillThesaurus();
    }
    private function loadCSVFile($file) {
      $handle = fopen($file, 'r');
      $row = 1;
      $head = array();
      $begin = 0;
      while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        switch (strtoupper($data[0])) {
          case 'URI':
           $begin = 1;
           $head = $data;
           break;
          case 'CONCEPTSCHEME URI':
            $this->scheme = $data[1];
            break;
          case 'PREFIX':
          case 'TITLE':
          case 'DESCRIPTION':
          	if(empty($this->{strtolower($data[0])})) {
              $this->{strtolower($data[0])} = $data[1];
          	}
            break;
        }
        /* if ($data[0] === 'URI') {
          $begin = 1;
          $head = $data;
        } else if ($data[0] === 'ConceptScheme URI') {
          $this->scheme = $data[1];
        } else if ($data[0] === 'PREFIX') {
          $this->prefix = $data[1];
        } */
        if ($begin > 1) {
          if(!empty($data[0])) {
            $concept = new Concept(array_combine($head, $data), $this->scheme, $this->prefix, 'csv2');
            $this->concepts[$concept->key] = $concept;
            
          }
        }
        if ($begin === 1) {
          $begin = 2;
        }
      }
      fclose($handle);

    }
    private function fillThesaurus() {
     
      // root have no broader
      $this->findRoots();
      // for all concept search all childs and fill narrower for childs
      $this->fillNarrower($this->roots);
    }
    private function fillNarrower($tab) {
      $childs = array();
      foreach($tab as $parentKey) {
        $childs = $this->findChilds($parentKey);
        $this->concepts[$parentKey]->narrower = $childs;
        if (count($childs) > 0) {
           $this->fillNarrower($childs);
        } else {
          array_push($this->sheets, $parentKey);
        }
      }
    }
    private function findChilds($parentKey) {
        $childs = array();
        foreach($this->concepts as $concept) {
          if ($concept->broader === $parentKey) {
            array_push($childs, $concept->key);
          }
        }
      return $childs;
    }
    private function findRoots() {
      $pos = current($this->concepts);
      $root = $pos;
      $done = [];
      $count = 0;
      while($pos) {
         $count ++;
          $root = $pos;
      
        array_unshift($pos->path, $pos->label['en']);
        while(!is_null($root->broader) && isset($this->concepts[$root->broader])) {
          array_push($done, $root->key);
          array_unshift($pos->path, $this->concepts[$root->broader]->label['en']);
          $root = $this->concepts[$root->broader];
        }
        if (!in_array( $root->key, $this->roots)) {
          array_push($this->roots, $root->key);
        }
        $this->length = max($this->length, count($pos->path));
        $pos = next($this->concepts);
        while ($pos && in_array( $pos->key, $done)) {
          next($this->concepts);
          $pos = current($this->concepts);
        }
      }
    }
 /*   public function extractGcmdToSkos() {
      
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
//       $domout = $this->initRdfDocument();
//       $root = $domout->documentElement;
//       header("Content-Type:text/xml");
//      echo $domout->saveXML();
    
    }

    public function createCSV($key, $filename) {
      $fp = fopen($filename, 'w');
      $this->headerCSV($fp);
      fputcsv($fp, self::$skosRow);
      $rows = array();
      $rows = $this->prepareRows($rows, $key);
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
    }*/

  
    private function initRdfDocument() {
      $domtree = new \DOMDocument('1.0', 'UTF-8');
      $xmlRoot = $domtree->createElement('rdf:RDF');
      $domtree->appendChild($xmlRoot);
      // $rdfNode = $domtree->createElement('rdf:RDF');
      $xmlRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
      $xmlRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:skos', 'http://www.w3.org/2004/02/skos/core#');
      if (!empty($this->title)) {
        $xmlRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        
        // $domtree->appendChild($rdfNode);
        $scheme = $domtree->createElement('skos:ConceptScheme');
        $scheme->setAttribute('rdf:about', $this->scheme);
        $title = $domtree->createElement('dc:title', $this->title);
        $scheme->appendChild($title);
        if (!empty($this->description)) {
          $description = $domtree->createElement('dc:description', $this->description);
          $scheme->appendChild($description);
        }
        $xmlRoot->appendChild($scheme);
        
      }
      return $domtree;
    }
    
}
