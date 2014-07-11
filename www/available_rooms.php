<?php

require('includes.php');
require('includes/common_booking.php');
require('../recepcio/room_booking.php');

$location = getLocation();
$lang = getCurrentLanguage();

if(isset($_REQUEST['source'])) {
	$_SESSION['booking_source'] = $_REQUEST['source'];
}

$_SESSION['booking_status'] = array('rooms');
if(isset($_REQUEST['from'])) {
	$_SESSION['from_date'] = $_REQUEST['from'];
	$_SESSION['to_date'] = $_REQUEST['to'];
	$_SESSION['nights'] = round((strtotime($_SESSION['to_date']) - strtotime($_SESSION['from_date'])) / (60*60*24));
}

if($_SESSION['from_date'] < date('Y-m-d')) {
	$_SESSION['booking_error'] = BOOKING_DATE_MUST_BE_IN_THE_FUTURE;
	header('Location: ' . $_SERVER['HTTP_REFERER']);
	return;
}

if($_SESSION['to_date'] <= date('Y-m-d')) {
	$_SESSION['booking_error'] = BOOKING_DATE_MUST_BE_IN_THE_FUTURE;
	header('Location: ' . $_SERVER['HTTP_REFERER']);
	return;
}

if($_SESSION['to_date'] <= $_SESSION['from_date']) {
	$_SESSION['booking_error'] = CHECKOUT_DATE_MUST_BE_AFTER_CHECKIN_DATE;
	header('Location: ' . $_SERVER['HTTP_REFERER']);
	return;
}



$link = db_connect($location);

$arriveDateTs = strtotime($_SESSION['from_date']);
$arriveDate = $_SESSION['from_date'];
$dateFormat = DATE_FORMAT;
if((strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')) {
	$dateFormat = str_replace('%e', '%#d', $dateFormat);
}
$arriveDateStr = strftime($dateFormat, $arriveDateTs);
$nights = $_SESSION['nights'];
$lastNightTs = strtotime($_SESSION['from_date'] . " +" . ($nights-1) . " day");
$lastNight = date('Y-m-d', $lastNightTs);


$checkin = CHECKIN;
$checkinDate = CHECKIN_DATE;
$checkoutDate = CHECKOUT_DATE;
$from = FROM_DATE;
$to = TO_DATE;
$fromDate = $_SESSION['from_date'];
$toDate = $_SESSION['to_date'];

$dateFormat = DATE_FORMAT_LONG;
if((strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')) {
	$dateFormat = str_replace('%e', '%#d', $dateFormat);
}
$arriveLongDate = strftime($dateFormat, strtotime($fromDate));
$departLongDate = strftime($dateFormat, strtotime($toDate));

$availableRooms = AVAILABILITY;
$arriveAt = sprintf(FROM_TO_DATES, $arriveLongDate, $departLongDate);
$specialOffer = SPECIAL_OFFER;
$changeDates = CHANGE_DATES;
$nightsTitle = NIGHTS;
$chooseLocation = CHOOSE_LOCATION;
$lodgeLocationSelected = ($location == 'lodge' ? ' selected="selected"' : '');
$lodgeTitle = LOCATION_NAME_LODGE;
$hostelLocationSelected = ($location == 'hostel' ? ' selected="selected"' : '');
$hostelTitle = LOCATION_NAME_HOSTEL;
$checkAvailability = CHECK_AVAILABILITY;
$roomDetails = ROOM_DETAILS;
$close = CLOSE;
$gallery = GALLERY;
$photos = PHOTOS;


$nightsOptions = "";
for($i = 1; $i < 15; $i++) {
	$nightsOptions .= "                    <option value=\"$i\"" . ($i == $nights ? ' selected="selected"' : '') . ">$i</option>\n";
}

$specialOfferSection = "";
$specialOffers = loadSpecialOffers("start_date<='$arriveDate' AND end_date>='$lastNight'", $link, $lang);
foreach($specialOffers as $soId => $so) {
	if(($so['nights'] == ($nights+1)) and is_null($so['room_type_ids'])) {
		$descr = $so['title'];
		$specialOfferSection .= <<<EOT
          <div>
            <h1>$specialOffer</h1>
            <p>$descr</p>
          </div>
EOT;

	}
}

if(strlen($specialOfferSection) > 0) {
	$specialOfferSection = <<<EOT
		<section id="special-offer" class="clearfix">
$specialOfferSection
        </section>

EOT;
}

$roomTypesData = loadRoomTypes($link, $lang);

$rooms = loadRooms(date('Y', $arriveDateTs), date('m', $arriveDateTs), date('d', $arriveDateTs), date('Y', $lastNightTs), date('m', $lastNightTs), date('d', $lastNightTs), $link, $lang);
foreach($rooms as $roomId => $roomData) {
	processRoomData($arriveDateTs, $nights, $roomData, $roomTypesData[$roomData['room_type_id']]);
}

uasort($roomTypesData, 'sortRoomsByAvailOrder');

html_start(AVAILABLE_ROOMS);

if(isset($_SESSION['booking_error'])) {
	$msg = $_SESSION['booking_error'];
	unset($_SESSION['booking_error']);
	echo <<<EOT
	  <script type="text/javascript">
        alert('$msg');
      </script>

EOT;
}

echo <<<EOT

      <h1 class="page-title page-title-availability">
        $availableRooms
      </h1>
      
      <div class="fluid-wrapper booking">
$specialOfferSection
        
        <section id="booking-filter">
          <h1 class="arrive">
            $arriveAt
            <span class="open-filter">$changeDates</span>
          </h1>

          <form class="filter" action="available_rooms.php" method="GET">
            <fieldset>
              <div class="date">
                <h2>$checkinDate</h2>
                <div class="field date from clearfix">
                  <label for="from">$from:</label>
                  <input type="date" id="from" name="from" value="$fromDate">
                </div>

                <h2>$checkoutDate</h2>
                <div class="field date to clearfix">
                  <label for="to">$to:</label>
                  <input type="date" id="to" name="to" value="$toDate">
                </div>
              </div>
              
              <div class="hostel">
                <h2>$chooseLocation</h2>
                
                <div class="fake-select">
                  <span class="value"></span>
                  <span class="open-select icon-down"></span>
                  <select name="location">
                    <option value="lodge"$lodgeLocationSelected>$lodgeTitle</option>
                    <option value="hostel"$hostelLocationSelected>$hostelTitle</option>
                  </select>
                </div>
                
                <div class="submit">
                  <button type="submit">$checkAvailability</button>
                </div>
              </div>
            </fieldset>
          </form>

        </section>


EOT;


$roomsTitle = ROOMS;
$details = DETAILS;
$guests = GUESTS;
$locationName = constant('LOCATION_NAME_' . strtoupper($location));
$extraServicesUrl = $location . '_extra_services.php';

echo <<<EOT
        <form class="update-summary" action="$extraServicesUrl" data-refresh="json_update_summary.php" method="post">
          <fieldset>
            <section class="rooms">
              <h1>$roomsTitle</h1>
              
              <ul>

EOT;

foreach($roomTypesData as $roomTypeId => $roomType) {
	$name = $roomType['name'];
	$descr = $roomType['description'];
	$shortDescr = $roomType['short_description'];
	$guestOptions = '';
	if(!isset($roomType['num_of_beds_avail'])) {
		$roomType['num_of_beds_avail'] = 0;
	}
	for($i = 0; $i <= $roomType['num_of_beds_avail']; $i++) {
		if($roomType['type'] != 'DORM' and ($i % $roomType['num_of_beds'] != 0)) {
			continue;
		}
		$key = 'room_type_' . $location . '_' . $roomTypeId;
		$selected = (isset($_SESSION[$key]) and $_SESSION[$key] == $i) ? ' selected="selected"' : '';
		$guestOptions .= "                      <option value=\"$i\"$selected>$i</option>\n";
	}
	$currency = getCurrency();
	$discount = 0;
	$sale = '';
	$specialOfferForOneMoreDay = null;
	foreach($specialOffers as $soId => $so) {
		if(specialOfferApplies($so, $roomType, $nights, $arriveDate)) {
			$discount = max($discount, $so['discount_pct']);
		}
		if(!specialOfferApplies($so, $roomType, $nights, $arriveDate) and specialOfferApplies($so, $roomType, $nights+1, $arriveDate)) {
			if(is_null($specialOfferForOneMoreDay) or ($specialOfferForOneMoreDay['discount_pct'] < $so['discount_pct'])) {
				$specialOfferForOneMoreDay = $so;
			}
		}
	}
	$price = $roomType['price'];
	$oldPricePerNight = '';
	if($discount > 0) {
		$percentOff = sprintf(PERCENT_OFF, $discount);
		$sale = <<<EOT
                    <p class="sale condensed">
                      <span>$percentOff</span>
                    </p>

EOT;
		$price = $price * (100 - $discount) / 100;
		$oldPricePerNight = sprintf($roomType['type'] == 'DORM' ? PRICE_PER_NIGHT_PER_BED : PRICE_PER_NIGHT_PER_ROOM, formatMoney(convertCurrency($roomType['price'], 'EUR', $currency), $currency)) . '<br>';
	}
	$pricePerNight = sprintf($roomType['type'] == 'DORM' ? PRICE_PER_NIGHT_PER_BED : PRICE_PER_NIGHT_PER_ROOM, formatMoney(convertCurrency($price, 'EUR', $currency), $currency));

	if($roomType['num_of_beds_avail'] > 0) {
		$formName = "room_type_" . $location . "_" . $roomTypeId;
		$selectionOfAvailability = <<<EOT
                  <div class="fake-select">
                    <label for="guests-room-1">$guests</label>
                    <span class="value"></span>
                    <span class="open-select icon-down"></span>
                    <select id="$formName" name="$formName">
$guestOptions
                    </select>
                  </div>

EOT;
	} else {
		$alreadyBooked = ALREADY_BOOKED;
		$selectionOfAvailability = <<<EOT
                  <p class="booked condensed">
                    $alreadyBooked
                  </p>


EOT;
	}

	$liClassAttr = '';
	$specialOfferHtml = '';
	if(!is_null($specialOfferForOneMoreDay)) {
		$liClassAttr = ' class="special-offer"';
		$title = $specialOfferForOneMoreDay['text'];
		$specialOfferHtml = <<<EOT
					  <p class="special-offer">
					    <strong>$specialOffer:</strong>
					    $title
					  </p>

EOT;
	}

	$sql = "SELECT * FROM room_images WHERE room_type_id=$roomTypeId";
	$result = mysql_query($sql, $link);
	$roomImg = '';
	if(mysql_num_rows($result) > 0) {
		while($row = mysql_fetch_assoc($result)) {
			if(($row['default'] == 1) or (strlen($roomImg) < 1)) {
				$host = '';
				if($location == 'hostel') {
					$host = 'http://img.maverickhostel.com/';
				}
				$roomImg = $host . 'get_image.php?type=ROOM&width=587&height=387&file=' . $row['filename'];
			}
		}
	}
	$extrasHtml = getExtrasHtml($location, $roomType['type']);

	echo <<<EOT
				<li$liClassAttr>
                  <div class="card clearfix">
                    <h2>
                      <a href="">$name</a>
                    </h2>              
                    <a class="open-overlay" href="" data-overlay-title="$gallery" data-overlay-gallery-url="gallery.php?room_type_id=$roomTypeId">
                      <img src="$roomImg" width="587" height="387">
                    </a>

                    <div class="data">
                      <p class="type condensed">
						<strong>$shortDescr</strong><br>
                        $locationName
                      </p>
$specialOfferHtml
$sale
                      <p class="price condensed"><span style="text-decoration:line-through;">$oldPricePerNight</span>$pricePerNight</p>
                    </div>
                    <p class="details-alternate">
                      <a class="open" href="">$roomDetails</a>
                      <a class="close" href="">$close</a>
                      <a class="open-overlay" href="" data-overlay-title="$gallery" data-overlay-gallery-url="gallery.php?room_type_id=$roomTypeId">
                        $photos
                      </a>
                    </p>

$selectionOfAvailability

				  </div>
                  <div class="extra clearfix">
                    <p class="details">$descr</p>
                    <ul class="extras">
$extrasHtml
                    </ul>
				  </div>
                </li>
                

EOT;
}

echo <<<EOT
              </ul>
            </section>

EOT;

echo getBookingSummaryHtml(CONTINUE_BOOKING);

echo <<<EOT
          </fieldset>
        </form>
      </div>

EOT;

html_end();
mysql_close($link);

function processRoomData($arriveTS, $nights, &$roomData, &$roomType) {
	$oneDayTS = $arriveTS;
	$type = $roomData['type'];
	$minAvailBeds = $roomType['num_of_beds'];
	$totalPrice = 0;
	for($i = 0; $i < $nights; $i++) {
		$currYear = date('Y', $oneDayTS);
		$currMonth = date('m', $oneDayTS);
		$currDay = date('d', $oneDayTS);
		$oneDay =  date('Y/m/d', $oneDayTS);
		$availBeds = getNumOfAvailBeds($roomData, $oneDay);
		// echo "For room:  " . $roomData['name'] . " (room type: " . $roomData['room_type_id'] . ") for day: $oneDay, there are $availBeds available beds<br>\n";
		$minAvailBeds = min($minAvailBeds, $availBeds);
		$oneDayTS += 24 * 60 * 60;
		if($type == 'DORM') {
			$totalPrice += getBedPrice($currYear, $currMonth, $currDay, $roomData);
		} else {
			$totalPrice += getRoomPrice($currYear, $currMonth, $currDay, $roomData);
		}

	}
	if($type == 'PRIVATE' and $minAvailBeds < $roomData['num_of_beds']) {
		$minAvailBeds = 0;
	}

	//set_debug("For room type: " . $roomType['name'] . ' adding available beds of ' . $minAvailBeds . ' with daily price: ' . ($totalPrice / $nights));

	if(!isset($roomType['num_of_beds_avail'])) {
		$roomType['num_of_beds_avail'] = 0;
	}
	$roomType['num_of_beds_avail'] += $minAvailBeds;
	$roomType['price'] = $totalPrice / $nights;
}


function sortRoomsByAvailOrder($rt1, $rt2) {
	if($rt1['num_of_beds_avail'] > $rt2['num_of_beds_avail']) {
		return -1;
	}
	if($rt1['num_of_beds_avail'] < $rt2['num_of_beds_avail']) {
		return 1;
	}
	if($rt1['_order'] < $rt2['_order']) {
		return -1;
	}
	if($rt1['_order'] > $rt2['_order']) {
		return 1;
	}
}


?>
