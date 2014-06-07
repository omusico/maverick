<?php

require('../includes.php');


html_start('TheMaverick', 'Hostel');

echo <<<EOT

<a href="#fotos">Fotos</a><br<br><br>>

<p>
Hier finden Sie eine breite Palette der Zimmer in der Herberge von unseren einzigartigen Schlafsaal zu intimen Doppelzimmer. Haben Sie keine Angst vor Etagebetten, weil wir sie aus dem Maverick verbannt haben. Bitte Schauen Sie sich um!
</p>



<div style="float: right; margin-right: 20px; width: 455px; height: 743px; background-color: #9DB1D6">
<img src="/images/Plan.jpg">
</div>

<br>
<p><strong style="font-size: 12px;">Mr Green- dormitory</strong><br>
Wir haben 10 Betten, umgeben von grünen Pflanzen im Wohnheim, von dem sind 4 auf dem Dachboden. Wenn Sie in der Stimmung sind zu Lesen, so zögern Sie nicht, unsere kostenlose Bibliothek zu nutzen.
</p>

<p><strong style="font-size: 12px;">Mss Peach- 5 beds</strong><br>
Wenn Sie in diesen 5 Betten Raum aufwachen, werden Sie von einem wunderbaren Kamin und ein weiches, durch die einzigartige Mosaik-Fenster leuchtendes Licht begrüßt. Möchten Sie diese Erfahrung genießen?
</p>

<p><strong style="font-size: 12px;">Mr and Mss Yellow- double room</strong><br>
Unsere intimen Doppelbett Zimmer mit TV sind in erster Linie für Paare entworfen. Es hat eine warme und freundliche Atmosphäre. Ein Zustellbett kann auf Anfrage hinzugefügt werden. 
</p>

<p><strong style="font-size: 12px;">Ms Lemon- double room</strong><br>
Unsere intimen Doppelbett Zimmer mit TV sind in erster Linie für Paare entworfen. Es hat eine warme und freundliche Atmosphäre. Ein Zustellbett kann auf Anfrage hinzugefügt werden. 
</p>

<p><strong style="font-size: 12px;">The  Blue Brothers- 6 beds</strong><br>
Dies ist unsere anderen Loft-Zimmer mit 3 Betten in dem Erdgeschoss und 3 mehr auf dem Obergeschoss. Aufgrund seiner Einrichtung ist für eine Gruppe von Freunden ideal, die die Stimmung eines gemütlichen Zimmer mit einem antiken Kamin genießen möchten.
</p>

<p>
<h2>Dienstleistungen</h2>

<ul>
	<li>24 Stunden Rezeption</li>
	<li>Flexibles check in / out</li>
	<li>Freies Internet und Wi-Fi Nutzung</li>
	<li>Bettwäsche inbehalten</li>
	<li>Wäscherei</li>
	<li>Kaffe und Tee kostenlos den ganzen Tag</li>
	<li>Handtücher sind in den Preis eingerechnet</li>
	<li>Lebensmittelgeschäft an der Ecke an unseren Gebäude, der auch nachts geöffnet hatt</li>
	<li>Kostenlose Tour den ganzen Tag</li>
	<li>Schließfächer</li>
	<li>Fön und Bügeleisen verfügbar</li>
	<li>Voll ausgestattete Küche </li>
	<li>Lift</li>
	<li>Rauchfrei Umwelt</li>
	<li>Transport vom Flughafen auf Anfrage</li>
	<li>Gratis-Bibliothek</li>
	<li>Sicherheits- Spinde</li>
	<li>Fahrradverleih</li>
	<li>Organisierte Touren, Führungen auf Anfrage.</li>
	<li>Öffentliche Verkehrsmittel beizuziehen.</li>
</ul>

<div style="clear:both;">
<br><br>
<h2><a name="fotos">Fotos</a></h2>

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
			if($images[$bigFile]['type'] != 'HOSTEL') {
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

html_end('TheMaverick', 'Hostel');

?>
