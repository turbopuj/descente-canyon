<?php

function extract_info($chaine,$start,$stop) {

	$s=(strpos($chaine,$start)+strlen($start));
	$l=strpos($chaine,$stop,$s)-$s;

	return (substr($chaine,$s,$l));

}


#Traitement des données du formulaire
$id=intval($_GET['id']);

if(empty($id)) {
	$url=$_GET['url'];
	preg_match_all('/http.*descente\-canyon.*\/([0-9]+)/',$url,$url_id);
	$id=($url_id[1])?intval($url_id[1][0]):0;
}

if(!$id) {
	echo "Erreur de saisie. Aucun identifiant valide saisie";
	exit;
}


$url_carte= 'https://www.descente-canyon.com/canyoning/canyon-carte/'.$id.'/carte.html';
$url_base= 'https://www.descente-canyon.com/canyoning/canyon/'.$id.'/';
$url_topo='https://www.descente-canyon.com/canyoning/canyon-description/'.$id.'/topo.html';
$url_debit='https://www.descente-canyon.com/canyoning/canyon-debit/'.$id.'/observations.html';

#Téléchargement du contenu des cartes et topos
$arrContextOptions=array(
      "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    );  

$carte = @file_get_contents($url_carte, false, stream_context_create($arrContextOptions));
$topo = @file_get_contents($url_topo, false, stream_context_create($arrContextOptions));

if(!$carte) {
	echo "Topo inexistant";
	exit;
}

#Préparation du fichier GPX
if($carte && $topo) {

	#Entete XML
	$display= '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>';
	$display.= '<gpx xmlns="http://www.topografix.com/GPX/1/1" creator="turbopuj" version="1.1" 
	    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
	    xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">';

	#Lien vers le topo en metadata
	$display.="
	<metadata>
		<link href=\"".$url_topo."\">
			<text>Lien vers le topo</text>
		</link>
	</metadata>\n";

	#Coordonnées
	preg_match_all('/var point =(.*);addMarker/U',$carte,$results);

	#Titre du topo
	$titre_topo=extract_info($topo,"<h1>","</h1>");
	$titre_clean=strip_tags($titre_topo);

	#Info du topo
	$info_topo=extract_info($topo,"<h3>Approche</h3>",'<br style="clear: both" />');
	$acces=extract_info($topo,"<h3>Accès</h3>","<h3>");
	$retour=extract_info($topo,"<h3>Retour</h3>","<h3>");
	
	#Fiche technique
	$fiche=extract_info($topo,'<div class="fichetechniqueintegree">',"</div>");
	preg_match_all('/\<span class\=\"badge\"\>(.*)\<\/span\>/U',$fiche,$badge_list);

	$badges=array_map('strip_tags',$badge_list[1]);

	$tech="<ul>";
	foreach ($badges as $key => $badge) {
		$tech.="<li>".$badge."</li>";
	}
	$tech.="</ul>";

	$descriptif_topo="<a href='".$url_debit."' alt='Débit'>[Voir Débit]</a><h3>Approche</h3>".$info_topo."<h3>Fiche technique</h3>".$tech;

	foreach ($results[1] as $key => $value) {

		preg_match('/LatLng\((.*)\)/U',$value,$coord);
		preg_match('/type\:\s\'(.*)\'/U',$value,$titre);
		preg_match('/remarque\:\s\'(.*)\'\,auteur/U',$value,$remarque);

		$remarque_point=($remarque[1])?" (".$remarque[1].")":"";

		$latlng=explode(",",$coord[1]);

		$display.= "	<wpt lat='".$latlng[0]."' lon='".$latlng[1]."'>\n";

		#Personnalisation des points GPS selon leur type (optimisé pour View Ranger)
		switch ($titre[1]) {
		    case "parking_amont":
		        $display.= "		<name>Parking amont".$remarque_point."</name>\n";
		        $display.= "		<sym>parking</sym>\n";
		        $display.= "		<type>parking</type>\n";
		        $display.= "		<cmt><![CDATA[".$acces."]]></cmt>\n";
		        $display.= "		<desc><![CDATA[".$acces."]]></desc>\n";
		        break;
		    case "parking_aval":
		        $display.= "		<name>Parking aval".$remarque_point."</name>\n";
		        $display.= "		<sym>parking</sym>\n";
		        $display.= "		<type>parking</type>\n";
		        $display.= "		<cmt><![CDATA[".$acces."]]></cmt>\n";
		        $display.= "		<desc><![CDATA[".$acces."]]></desc>\n";
		        break;
		   	case "parking":
		        $display.= "		<name>Parking".$remarque_point."</name>\n";
		        $display.= "		<sym>parking</sym>\n";
		        $display.= "		<type>parking</type>\n";
		        $display.= "		<cmt><![CDATA[".$acces."]]></cmt>\n";
		        $display.= "		<desc><![CDATA[".$acces."]]></desc>\n";
		        break;
		    case "depart":
		        $display.= "		<name>Départ ".$titre_clean.$remarque_point."</name>\n";
		        $display.= "		<sym>place</sym>\n";
		        $display.= "		<type>place</type>\n";
		        $display.= "		<cmt><![CDATA[".$descriptif_topo."]]></cmt>\n";
		        $display.= "		<desc><![CDATA[".$descriptif_topo."]]></desc>\n";
		        $display.= "		<link href='".$url_topo."'><text>Lien vers le topo</text></link>\n";
		        break;
		    case "arrivee":
		    	$display.= "		<name>Arrivée ".$titre_clean.$remarque_point."</name>\n";
		        $display.= "		<sym>warningflag</sym>\n";
		        $display.= "		<type>warningflag</type>\n";
		        $display.= "		<cmt><![CDATA[".$retour."]]></cmt>\n";
		        $display.= "		<desc><![CDATA[".$retour."]]></desc>\n";
		        break;
		    default:
		    	$display.= "		<name>".$titre[1].$remarque_point."</name>\n";
		    	break;
	   }
		$display.= "	</wpt>\n";
	}

	$display.= "</gpx>\n";

	// // Création du fichier GPX
	$temp_file = tempnam(sys_get_temp_dir(), 'canyon_gpx');
	file_put_contents($temp_file,$display);

	header("Content-Length: " . filesize($temp_file));
	header('Content-type: application/gpx');
	header("Content-Disposition: attachment; filename=canyon".$id.".gpx");
	readfile($temp_file);
	unlink($temp_file);
}
?>


