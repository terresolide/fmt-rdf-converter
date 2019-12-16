<?php

namespace App\Controllers;

use \Core\View;
// use \EasyRdf\Graph as RdfGraph;
use \App\Models\Thesaurus;

/**
 * Home controller
 *
 * PHP version 7.0
 */
class Convert extends \Core\Controller
{

    
    private static $phpFileUploadErrors = array(
      0 => 'Le fichier a été téléchargé avec succès',
      1 => 'La taille du fichier excède la taille maximale accéptée par le serveur',
      2 => 'La taille du fichier excède la taille maximale configurée dans cette application',
      3 => 'Le fichier a été partiellement téléchargé',
      4 => 'Aucun fichier téléchargé',
      6 => 'Il manque un répertoire temporaire pour les téléchargements',
      7 => 'Impossible d\'écrire sur le disque',
      8 => 'Une extension PHP a stoppé le téléchargement',
    );
   
    /**
     * Show the upload page or response to upload
     *
     * @return void
     */
    public function indexAction()
    {
      $error = NULL;
      if (isset($_FILES['file'])) {
        $error = $this->treatment($_FILES['file']);
      }
      View::renderTemplate('Convert/index.html', array('error' => $error));
    }
    
    
    private function getOptions() {
      // get post parameters
      return $_POST;
    }
    private function treatment($files) {
      if (isset($files['error'])) {
      	$success = true;
      	$msg = '<ul>';
      	foreach($files['error'] as $index => $error) {
      		if ($error !== UPLOAD_ERR_OK) {
      			$success = false;
      			$msg .= '<li>'. $files['name'][$index]. ' &#x2192; ' . self::$phpFileUploadErrors[$error] . '</li>';
      		}

      	}
      	if (!$success) {
           return 'Une erreur est survenue lors du téléchargement: ' . $msg . '</ul>';
      	}
      } 
      $options = $this->getOptions();
      switch($options['type']) {
        case 'rdf':
        	$this->treatmentRDF($files, $options);
          exit;
          break;
        case 'csv2':
        case 'csv':
        	$this->treatmentCSV($files, $options);
           exit;
          break;
        default:
        	return 'Le type de fichier ' . $_FILES['file']['type'] . ' n\'est pas pris en charge.';
      	
      }
       
    }
    private function treatmentCSV($files, $options) {
      $thesaurus = new Thesaurus($files, $options);
      //header('Content-type: text/csv');
     // header('Content-disposition: attachment;filename=export.csv');
     // $thesaurus->toGCMD();
      header("Content-Type:text/xml");
      echo $thesaurus->toRDF()->saveXml();
    }
    private function treatmentRDF($file, $options) {
    	$domin = new \DOMDocument();
    	$domin->load($file['tmp_name']);
    	$rootin = $domin->documentElement;
    
    }
}
