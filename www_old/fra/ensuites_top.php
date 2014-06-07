<?php

require('../includes.php');


html_start('TheMaverick', 'EnsuitesTop', 'Maverick Ensuites en haut - Chambres avec lits doubles et salle de bains');

echo <<<EOT

<div style="float: right; margin-right: 20px; width: 503px; height: 734px; background-color: #9DB1D6">
<img src="/images/Plan_ensuite_top.jpg">
</div>


<a href="#photos">Photos</a><br><br><br>

<p>
Ces appartements se situent au dernier étage avec leur propre salle de bain, la télévision par câble et accès Wifi.
<br>
Toutes les chambres, les pièces communes et la cuisine ont un décor unique et innovant.
<br>
Des lits supplémentaires peuvent être délivrés dans chaque chambre sur simple demande.

</p>

<p>
<h2>Services</h2>

<ul>
	<li>Reception 24 heures</li>
	<li>Check in/out flexible</li>
	<li>Internet, Wi-Fi GRATUIT</li>
	<li>Café et thé GRATUIT tout la journe</li>
	<li>Tour guidé gratuit tous les jours,seulement pour en pourboir.</li>
	<li>Serviette COMPRISE</li>
	<li>Set de lit COMPRISE</li>
	<li>Cuisine equippé</li>
	<li>Lavage available</li>
	<li>Ascenseur</li>
	<li>Endroit non fumeur</li>
	<li>Transfer de l'aéroport</li>
	<li>Bibliothéque Gratuit</li>
	<li>Lockers</li>
	<li>Vélo á louer</li>
	<li>Tour organisé</li>
	<li>Conseil en transport public</li>
</ul>

<div style="clear:both;">
<br><br>

<h2><a name="photos">Photos</a></h2>


EOT;

$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
mysql_select_db(DB_NAME, $link);

$currLang = getCurrentLanguage();

$sql = "SELECT images.*, lang_text.value AS description FROM images LEFT OUTER JOIN lang_text ON (lang_text.table_name='images' AND lang_text.column_name='description' AND row_id=images.id AND lang_text.lang='$currLang')";
$result = mysql_query($sql, $link);
$images = array();
while($row = mysql_fetch_assoc($result)) {
	$images[$row['filename']] = $row;
}

if ($dh = opendir(PHOTOS_DIR)) {
	$hidden = "";
	while ($file = readdir($dh)) {
		if(is_dir(PHOTOS_DIR . "/" . $file))
			continue;
		if(substr($file, 0, 7) != '_thumb_')
			continue;

		$hidden .= "<img src=\"" . PHOTOS_URL . "/$bigFile\">";
		$bigFile = substr($file, 7);
		$descr = '';
		if(isset($images[$bigFile])) {
			$descr = $images[$bigFile]['description'];
			if($images[$bigFile]['type'] != 'ENSUITE_TOP') {
				continue;
			}
		} else {
			continue;
		}

		echo "<div class=\"photo\"><img src=\"" . PHOTOS_URL . "/$file\" onmouseover=\"Tip('<img src=\'" . PHOTOS_URL . "/$bigFile\'>', TITLE, '$descr', BORDERCOLOR, '#ffffff', BORDERWIDTH, 7, PADDING, 0, SHADOW, true, SHADOWWIDTH, 7, SHADOWCOLOR, '#555555', CENTERMOUSE, true, OFFSETX, 0, CLOSEBTN, true, FIX, [CalcFixX(), CalcFixY()], CLICKCLOSE, true, STICKY, true, DURATION, 5000);\" onmouseout=\"UnTip();\"/><div class=\"title\">$descr</div></div>\n";

	}
	closedir($dh);
	echo "<div style=\"clear: both;\"></div><div style=\"display: none;\">$hidden</div>\n";
}

mysql_close($link);


html_end('TheMaverick', 'EnsuitesTop');

?>
