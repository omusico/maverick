<?php

function loadRoomTypes($link, $lang = 'eng') {
	$sql = "SELECT rt.id, rt.price_per_bed, rt.price_per_room, rt.type, rt.num_of_beds, lt1.value AS name, lt2.value AS description, lt3.value AS short_description, rt._order FROM room_types rt " . 
	"INNER JOIN lang_text lt1 ON (lt1.table_name='room_types' AND lt1.column_name='name' AND lt1.row_id=rt.id AND lt1.lang='$lang') " . 
	"INNER JOIN lang_text lt2 ON (lt2.table_name='room_types' AND lt2.column_name='description' AND lt2.row_id=rt.id AND lt2.lang='$lang') " . 
	"LEFT OUTER JOIN lang_text lt3 ON (lt3.table_name='room_types' AND lt3.column_name='short_description' AND lt3.row_id=rt.id AND lt3.lang='$lang') ORDER BY rt._order";

	$result = mysql_query($sql, $link);
	$roomTypesData = array();
	while($row = mysql_fetch_assoc($result)) {
		$roomTypesData[$row['id']] = $row;
	}

	return $roomTypesData;
}

function loadRoomTypesWithAvailableBeds($link, $startDate, $endDate, $lang = 'eng') {
	$roomTypesData = loadRoomTypes($link, $lang);
	$sql = "SELECT rt.id, COUNT(*)*rt.num_of_beds AS available_beds, COUNT(*) AS num_of_rooms FROM room_types rt INNER JOIN rooms r ON rt.id=r.room_type_id WHERE r.valid_from<='" . str_replace('-', '/', $startDate) . "' and r.valid_to>='" . str_replace('-', '/', $endDate) . "' GROUP BY rt.id";
	$result = mysql_query($sql, $link);
	if(!$result) {
		echo "SQL ERROR: " . mysql_error($link) . " (sql: $sql)<br>\n";
	} else {
		while($row = mysql_fetch_assoc($result)) {
			$oneRoomType = $roomTypesData[$row['id']];
			$oneRoomType['available_beds'] = $row['available_beds'];
			$oneRoomType['num_of_rooms'] = $row['num_of_rooms'];
			$roomTypesData[$row['id']] = $oneRoomType;
		}
	}
	return $roomTypesData;
}



function loadSpecialOffers($whereClause, $link, $lang = 'eng') {
	$sql = "SELECT so.*, n.value AS title, d.value AS text, r.value AS room_name FROM special_offers so " .
		"INNER JOIN lang_text n ON (so.id=n.row_id AND n.table_name='special_offers' AND n.column_name='title' AND n.lang='$lang') " .
		"INNER JOIN lang_text d ON (so.id=d.row_id AND d.table_name='special_offers' AND d.column_name='text' AND d.lang='$lang') " .
		"LEFT OUTER JOIN lang_text r ON (so.id=r.row_id AND r.table_name='special_offers' AND r.column_name='room_name' AND r.lang='$lang') " .
		"WHERE $whereClause";
	$result = mysql_query($sql, $link);
	$specialOffers = array();
	while($row = mysql_fetch_assoc($result)) {
		$specialOffers[$row['id']] = $row;
	}

	return $specialOffers;
}


function &loadRooms($startYear, $startMonth, $startDay, $endYear, $endMonth, $endDay, $link, $lang = 'eng') {
	$startMonth = __getNormalizedDate($startMonth);
	$startDay = __getNormalizedDate($startDay);
	$endMonth = __getNormalizedDate($endMonth);
	$endDay = __getNormalizedDate($endDay);

	$arriveDate = "$startYear/$startMonth/$startDay";
	$lastNightDate = "$endYear/$endMonth/$endDay";

	$rooms = array();
	$sql = "SELECT r.id, r.room_type_id, r.name AS name, rt.name AS room_type_name, rt.type, rt.num_of_beds, rt.price_per_room, rt.price_per_bed, rt._order FROM rooms r INNER JOIN room_types rt ON (r.room_type_id=rt.id) WHERE r.valid_to>='$lastNightDate' AND r.valid_from<='$arriveDate'";
	$result = mysql_query($sql, $link);
	if(!$result) {
		trigger_error("Cannot get rooms: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
		return false;
	}
	while($row = mysql_fetch_assoc($result)) {
		$row['bookings'] = array();
		$row['prices'] = array();
		$row['room_changes'] = array();
		$rooms[$row['id']] = $row;
	}

	$roomChanges = array();
	$sql = "SELECT brc.*, bd.name, bd.name_ext, b.description_id, b.room_payment, b.booking_type, b.num_of_person, b.creation_time, bd.first_night, bd.last_night, bd.num_of_nights, bd.confirmed, bd.cancelled, bd.checked_in, bd.paid FROM booking_room_changes brc INNER JOIN bookings b ON brc.booking_id=b.id INNER JOIN booking_descriptions bd ON b.description_id=bd.id WHERE brc.date_of_room_change>='$arriveDate' AND brc.date_of_room_change<='$lastNightDate'";
	$result = mysql_query($sql, $link);
	if(!$result) {
		trigger_error("Cannot get existing bookings: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
		return false;
	}
	while($row = mysql_fetch_assoc($result)) {
		if(isset($rooms[$row['new_room_id']])) {
			$row['payments'] = array();
			$row['service_charges'] = array();
			$roomChanges[$row['booking_id']][] = $row;
			$rooms[$row['new_room_id']]['room_changes'][] = $row;
		}
	}

	$sql = "SELECT bd.name, bd.name_ext, bd.confirmed, bd.checked_in, bd.cancelled, bd.cancel_type, bd.paid, bd.first_night, bd.arrival_time, bd.last_night, bd.num_of_nights, b.* FROM bookings b INNER JOIN booking_descriptions bd ON b.description_id=bd.id WHERE bd.first_night<='$lastNightDate' AND bd.last_night>='$arriveDate'";
	$result = mysql_query($sql, $link);
	if(!$result) {
		trigger_error("Cannot get existing bookings between dates: $arriveDate and $lastNightDate: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
		return false;
	}
	$descrIds = array();
	while($row = mysql_fetch_assoc($result)) {
		if(isset($rooms[$row['room_id']])) {
			$row['changes'] = array();
			if(isset($roomChanges[$row['id']])) {
				$row['changes'] = $roomChanges[$row['id']];
			}
			$row['payments'] = array();
			$row['service_charges'] = array();
			$rooms[$row['room_id']]['bookings'][] = $row;
			$descrIds[] = $row['description_id'];
		}
	}

	if(count($descrIds) > 0) {
		$descrIds = implode(',', $descrIds);
		$sql = "SELECT * FROM payments WHERE booking_description_id IN ($descrIds)";
		$result = mysql_query($sql, $link);
		if(!$result) {
			trigger_error("Cannot get payments when loading rooms: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
		} else {
			while($row = mysql_fetch_assoc($result)) {
				foreach($rooms as $roomId => $roomData) {
					for($i = 0; $i < count($rooms[$roomId]['bookings']); $i++) {
						if($rooms[$roomId]['bookings'][$i]['description_id'] == $row['booking_description_id']) {
							$rooms[$roomId]['bookings'][$i]['payments'][] = $row;
						}
					}
					for($i = 0; $i < count($rooms[$roomId]['room_changes']); $i++) {
						if($rooms[$roomId]['room_changes'][$i]['description_id'] == $row['booking_description_id']) {
							$rooms[$roomId]['room_changes'][$i]['payments'][] = $row;
						}
					}

				}
			}
		}
		$sql = "SELECT * FROM service_charges WHERE booking_description_id IN ($descrIds)";
		$result = mysql_query($sql, $link);
		if(!$result) {
			trigger_error("Cannot get service chanrges when loading rooms: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
		} else {
			while($row = mysql_fetch_assoc($result)) {
				foreach($rooms as $roomId => $roomData) {
					for($i = 0; $i < count($rooms[$roomId]['bookings']); $i++) {
						if($rooms[$roomId]['bookings'][$i]['description_id'] == $row['booking_description_id']) {
							$rooms[$roomId]['bookings'][$i]['service_charges'][] = $row;
						}
					}
					for($i = 0; $i < count($rooms[$roomId]['room_changes']); $i++) {
						if($rooms[$roomId]['room_changes'][$i]['description_id'] == $row['booking_description_id']) {
							$rooms[$roomId]['room_changes'][$i]['service_charges'][] = $row;
						}
					}
				}
			}
		}

	}

	//set_debug("Rooms: " . print_r($rooms, true));

	$sql = "SELECT * FROM prices_for_date WHERE date<='$lastNightDate'  AND date>='$arriveDate'";
	$result = mysql_query($sql, $link);
	if(!$result) {
		trigger_error("Cannot get existing room prices when loading rooms data: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
	}

	$pricesForRoomType = array();
	while($row = mysql_fetch_assoc($result)) {
		if(!isset($pricesForRoomType[$row['room_type_id']])) {
			$pricesForRoomType[$row['room_type_id']] = array();
		}
		$pricesForRoomType[$row['room_type_id']][] = $row;
	}

	foreach($rooms as $roomId => $roomData) {
		if(isset($pricesForRoomType[$roomData['room_type_id']])) {
			foreach($pricesForRoomType[$roomData['room_type_id']] as $price) {
				//set_debug("Setting price for room. Price: " . print_r($price, true));
				$rooms[$roomId]['prices'][$price['date']] = $price;
			}
		}
	}

	//set_debug("Rooms: " . print_r($rooms, true));
	//set_debug("Room changes: " . print_r($roomChanges, true));


	uasort($rooms, 'cmpRoomByOrder');

	return $rooms;
}

function cmpRoomByOrder($room1, $room2) {
	$retVal = ($room1['_order'] < $room2['_order'] ? -1 : ($room1['_order'] > $room2['_order'] ? 1 : 0));
	if($retVal === 0) {
		$retVal = ($room1['name'] < $room2['name'] ? -1 : ($room1['name'] > $room2['name'] ? 1 : 0));
	}
	return $retVal;
}


function isPrivate(&$roomData) {
	return $roomData['type'] == 'PRIVATE';
}

function isDorm(&$roomData) {
	return $roomData['type'] == 'DORM';
}


/**
 * Get the price of a booking for the specified interval. If the type is ROOM, the
 * function returns the room price, if the booking is BED it returns the price
 * of a bed. Parameters: 
 *  - Start and End dates are in format yyyy-mm-dd
 *  - type is 'BED' or 'ROOM'
 *  - room is the array that is an element of the array returned from the loadRooms() function.
 */
function getPriceForInterval($startDate, $endDate, $type, &$room) {
	$startDate = str_replace('/', '-', $startDate);
	$endDate = str_replace('/', '-', $endDate);
	list($startYear, $startMonth, $startDay) = explode('-', $startDate);
	list($endYear, $endMonth, $endDay) = explode('-', $endDate);
	$startMonth = __getNormalizedDate($startMonth);
	$startDay = __getNormalizedDate($startDay);
	$endMonth = __getNormalizedDate($endMonth);
	$endDay = __getNormalizedDate($endDay);
	$startDate = "$startYear-$startMonth-$startDay";
	$endDate = "$endYear-$endMonth-$endDay";
	set_debug("getPriceInterval() start: $startDate, end: $endDate, room: " . $room['name']);
	$payment = 0;
	for($currDate = $startDate; $currDate <= $endDate; $currDate = date('Y-m-d', strtotime("$currDate +1 day"))) {
		$currYear = substr($currDate, 0, 4);
		$currMonth = substr($currDate, 5, 2);
		$currDay = substr($currDate, 8, 2);
		if(strlen($currMonth) < 2) $currMonth = '0' . $currMonth;
		if(strlen($currDay) < 2) $currDay = '0' . $currDay;
		if($type == 'BED') {
			$payment += getBedPrice($currYear, $currMonth, $currDay, $room);
		} else {
			$payment += getRoomPrice($currYear, $currMonth, $currDay, $room);
		}
		set_debug("getPriceInterval() - payment now: $payment");
	}
	return $payment;
}


function getPrice($arriveTS, $nights, &$roomData) {
	$oneDayTS = $arriveTS;
	$type = $roomData['type'];
	$totalPrice = 0;
	for($i = 0; $i < $nights; $i++) {
		$currYear = date('Y', $oneDayTS);
		$currMonth = date('m', $oneDayTS);
		$currDay = date('d', $oneDayTS);
		$oneDay =  date('Y/m/d', $oneDayTS);
		$oneDayTS += 24 * 60 * 60;
		if($type == 'DORM') {
			$totalPrice += getBedPrice($currYear, $currMonth, $currDay, $roomData);
		} else {
			$totalPrice += getRoomPrice($currYear, $currMonth, $currDay, $roomData);
		}
	}

	return $totalPrice;
}




function getBedPrice($year, $month, $day, &$room) {
	$retVal = null;
	$month = __getNormalizedDate($month);
	$day = __getNormalizedDate($day);
	$dt = $year . '/' . $month . '/' . $day;
	if(isset($room['prices'][$dt])) {
		if(is_null($room['prices'][$dt]['price_per_bed']))
			$retVal = $room['prices'][$dt]['price_per_room'] / $room['num_of_beds'];
		else
			$retVal = $room['prices'][$dt]['price_per_bed'];
	} else {
		if(is_null($room['price_per_bed']))
			$retVal = $room['price_per_room'] / $room['num_of_beds'];
		else
			$retVal = $room['price_per_bed'];
	}
	return $retVal;
}



function getRoomPrice($year, $month, $day, &$room) {
	$retVal = null;
	$month = __getNormalizedDate($month);
	$day = __getNormalizedDate($day);
	$dt = $year . '/' . $month . '/' . $day;
	if(isset($room['prices'][$dt])) {
		if(is_null($room['prices'][$dt]['price_per_room']))
			$retVal = $room['prices'][$dt]['price_per_bed'] * $room['num_of_beds'];
		else
			$retVal = $room['prices'][$dt]['price_per_room'];
	} else {
		if(is_null($room['price_per_room']))
			$retVal = $room['price_per_bed'] * $room['num_of_beds'];
		else
			$retVal = $room['price_per_room'];
	}
	return $retVal;
}





function getNumOfAvailBeds(&$oneRoom, $oneDay, $excludeBookingWithId = null, $excludeBookingWithDescrId = null) {
	$occupBeds = getNumOfOccupBeds($oneRoom, $oneDay, $excludeBookingWithId, $excludeBookingWithDescrId);
	$avail = max(0, $oneRoom['num_of_beds'] - $occupBeds);
	return $avail;
}





function getNumOfOccupBeds(&$oneRoom, $oneDay, $excludeBookingWithId = null, $excludeBookingWithDescrId = null) {
	$occupBeds = 0;
	$oneDay = str_replace('-', '/', $oneDay);
	list($year, $month, $day) = explode('/', $oneDay);
	$month = __getNormalizedDate($month);
	$day = __getNormalizedDate($day);
	$oneDate = "$year/$month/$day";

	foreach($oneRoom['bookings'] as $oneBooking) {
		if($oneBooking['cancelled'] == 1) {
			continue;
		}

		if($oneBooking['id'] == $excludeBookingWithId) {
			continue;
		}

		if($oneBooking['description_id'] == $excludeBookingWithDescrId) {
			continue;
		}

		if(isset($oneBooking['changes'])) {
			$isThereRoomChangeForDay = false;
			foreach($oneBooking['changes'] as $oneChange) {	
				if($oneChange['date_of_room_change'] == $oneDay) {
					$isThereRoomChangeForDay = true;
				}
			}
			if($isThereRoomChangeForDay)
				continue;
		}

		if(($oneBooking['first_night'] <= $oneDay) and ($oneBooking['last_night'] >= $oneDay)) {
			if($oneBooking['booking_type'] == 'BED')
				$occupBeds += $oneBooking['num_of_person'];
			else {
				$occupBeds = $oneRoom['num_of_beds'];
				break;
			}
		}
	}

	foreach($oneRoom['room_changes'] as $oneRoomChange) {
		if($oneRoomChange['cancelled'] == 1) {
			continue;
		}
		if($oneRoomChange['booking_id'] == $excludeBookingWithId) {
			continue;
		}
		if($oneRoomChange['description_id'] == $excludeBookingWithDescrId) {
			continue;
		}


		if($oneRoomChange['date_of_room_change'] == $oneDay) {
			if($oneRoomChange['booking_type'] == 'BED')
				$occupBeds += $oneRoomChange['num_of_person'];
			else {
				$occupBeds = $oneRoom['num_of_beds'];
				break;
			}
		}
	}

	return $occupBeds;
}



/**
 * Returns an array where the key is the roomTypeId and the values is an array 
 * of dates for which there is no available space.
 */
function getOverbookings($numOfPersonForRoomType, $startDate, $endDate, &$rooms) {
	$startDate = str_replace('/', '-', $startDate);
	$endDate = str_replace('/', '-', $endDate);
	list($startYear, $startMonth, $startDay) = explode('-', $startDate);
	list($endYear, $endMonth, $endDay) = explode('-', $endDate);
	$startMonth = __getNormalizedDate($startMonth);
	$startDay = __getNormalizedDate($startDay);
	$endMonth = __getNormalizedDate($endMonth);
	$endDay = __getNormalizedDate($endDay);
	$startDate = "$startYear-$startMonth-$startDay";
	$endDate = "$endYear-$endMonth-$endDay";
	set_debug("getOverbookings() - From: $startDate, To: $endDate");
	$overbookings = array();
	foreach($numOfPersonForRoomType as $roomTypeId => $numOfPerson) {
		$datesUnavailable = array();
		set_debug("getOverbookings() - checking room ($roomTypeId)");
		for($currDate = $startDate; $currDate <= $endDate; $currDate = date('Y-m-d', strtotime("$currDate +1 day"))) {
			$availableBeds = 0;
			foreach($rooms as $roomId => $roomData) {
	//			set_debug("for room id: $oneRoomId, the data is: " . print_r($rooms[$oneRoomId], true));
				if($roomData['room_type_id'] != $roomTypeId) {
					continue;
				}
				$availableBeds += getNumOfAvailBeds($rooms[$roomId], $currDate);
			}
			set_debug("getOverbookings() - Available beds: $availableBeds for date: $currDate");

			if($availableBeds < $numOfPerson) {
				$datesUnavailable[$currDate] = $availableBeds;
			}
		}
		if(count($datesUnavailable) > 0) {
			$overbookings[$roomTypeId] = $datesUnavailable;
		}
	}

	set_debug("getOverbookings() - returning: " . print_r($overbookings, true));
	return $overbookings;
}


//
// Returns a assoc array with key: roomTypeId, value the booking data for
// the room type
// Input params:
//   location: hostel or lodge (used to get the selected room from SESSION
//   arriveDateTs: timestamp of arrive date
//   nights: number of nights
//   roomTypesData: result of the loadRoomTypes() function
//   rooms: result of the loadRooms() function
//   specialOffers: 
function getBookingsWithDiscount($location, $arriveDateTs, $nights, &$roomTypesData, &$rooms, $specialOffers) {
	$arriveDate = date('Y-m-d', $arriveDateTs);
	$retVal = array();
	foreach($roomTypesData as $roomTypeId => $roomType) {
		$numOfGuests = 0;
		$key = 'room_type_' . $location . '_' . $roomTypeId;
		if(isset($_REQUEST[$key])) {
			$_SESSION[$key] = $_REQUEST[$key];
		}
		if(isset($_SESSION[$key])) {
			$numOfGuests = $_SESSION[$key];
		}
		if($numOfGuests > 0) {
			$name = $roomType['name'];
			$rtId = $roomType['id'];
			$roomData = getRoomData($rooms, $roomTypeId);
			$price = getPrice($arriveDateTs, $nights, $roomData);
			if($roomType['type'] == 'DORM') {
				$price = $price * $numOfGuests;
			} else {
				$price = $price * ceil($numOfGuests / $roomType['num_of_beds']);
			}
			$discount = 0;
			$selectedSo = null;
			foreach($specialOffers as $so) {
				if(specialOfferApplies($so, $roomType, $nights, $arriveDate) and $so['discount_pct'] > $discount ) {
					$discount = $so['discount_pct'];
					$selectedSo = $so;
				}
			}
			// apply special offer
			$discountedPrice = $price;
			if($discount > 0) {
				$discountedPrice = $price * (100 - $discount) / 100;
			}

			$oneRoom = array('roomName' => $name, 'roomTypeId' => $rtId, 'numOfGuests' => $numOfGuests, 'price' => $price, 'discountedPrice' => $discountedPrice);
				
			if(!is_null($selectedSo)) {
				$oneRoom['specialOfferId'] = $selectedSo['id'];
			}
			$retVal[$rtId] = $oneRoom;
		}
	}

	return $retVal;
}

function specialOfferApplies($specialOffer, $roomType, $nights, $arriveDate) {
	set_debug("Checking if special offer applies to arrive date: $arriveDate, nights: $nights, roomType: " . print_r($roomType, true) . ", special offer: " . print_r($specialOffer, true));
	if(!is_null($specialOffer['room_type_ids']) and strpos($specialOffer['room_type_ids'],$roomType['id']) === false) {
		return false;
	}

	if(!is_null($specialOffer['nights']) and $nights != $specialOffer['nights']) {
		return false;
	}

	if(!is_null($specialOffer['valid_num_of_days_before_arrival'])) {
		$cutoffDate = date('Y-m-d', strtotime(date('Y-m-d') . ' +' . $specialOffer['valid_num_of_days_before_arrival'] . ' day'));
		if($arriveDate > $cutoffDate) {
			return false;
		}
	}
	set_debug("it applies!");

	return true;
}


function getRoomData(&$rooms, $roomTypeId) {
	foreach($rooms as $rid => $roomData) {
		if($roomData['room_type_id'] == $roomTypeId) {
			return $roomData;
		}
	}
	return null;
}




// creates two arrays: 
// $toBook that contains the roomId as a key and the value contains the number of people and the type (ROOM or BED) of the booking.
// $roomChanges where the key is a roomId and the value is an array of date => new_room_id
function getBookingData($numOfPersonForRoomType, $startDate, $endDate, &$rooms, &$roomTypes) {
	$startDate = str_replace('/', '-', $startDate);
	$endDate = str_replace('/', '-', $endDate);
	list($startYear, $startMonth, $startDay) = explode('-', $startDate);
	list($endYear, $endMonth, $endDay) = explode('-', $endDate);
	$startMonth = __getNormalizedDate($startMonth);
	$startDay = __getNormalizedDate($startDay);
	$endMonth = __getNormalizedDate($endMonth);
	$endDay = __getNormalizedDate($endDay);
	$startDate = "$startYear-$startMonth-$startDay";
	$endDate = "$endYear-$endMonth-$endDay";
	$toBook = array();
	$roomChanges = array(); // contains the rooms where some days has to be spent in another room
	foreach($numOfPersonForRoomType as $roomTypeId => $numOfPerson) {
		$roomType = $roomTypes[$roomTypeId];
		set_debug("getBookingData() - Checking room type: " . $roomType['name'] . ". There are $numOfPerson  person for that room(s)");
/*
		if($roomType['type'] == 'DORM') {
			$toBook[$roomIds[0]] = array('num_of_person' => $numOfPerson, 'type' => 'BED');
		} elseif(count($roomIds) == 1) {
			$toBook[$roomIds[0]] = array('num_of_person' => $roomType['num_of_beds'], 'type' => 'ROOM');
		} else {
*/

		$roomsNotToBook = array();
		for($currDate = $startDate; $currDate <= $endDate; $currDate = date('Y-m-d', strtotime("$currDate +1 day"))) {
			foreach($rooms as $roomId => $roomData) { 
				if($roomData['room_type_id'] != $roomTypeId) {
					continue;
				}
				$availableBeds = getNumOfAvailBeds($roomData, $currDate);
				if($roomType['type'] == 'PRIVATE' and $availableBeds != $roomType['num_of_beds']) {
					$roomsNotToBook[$roomId][] = $currDate;
				} elseif($roomType['type'] == 'DORM' and $availableBeds < $numOfPerson)
					$roomsNotToBook[$roomId][] = $currDate;
			}
		}

		$numOfBedsBooked = 0;
		foreach($rooms as $roomId => $roomData) { 
			if($roomData['room_type_id'] != $roomTypeId) {
				continue;
			}
			if($numOfPerson <= $numOfBedsBooked) {
				break;
			}
			if(!isset($roomsNotToBook[$roomId])) {
				if($roomType['type'] == 'PRIVATE') {
					$toBook[$roomId] = array('num_of_person' => $roomData['num_of_beds'], 'type' => 'ROOM');
					$numOfBedsBooked += $roomData['num_of_beds'];
				} elseif($roomType['type'] == 'DORM') {
					$numOfPersonInDorm = min($roomData['num_of_beds'], $numOfPerson-$numOfBedsBooked);
					$toBook[$roomId] = array('num_of_person' => $numOfPersonInDorm, 'type' => 'BED');
					$numOfBedsBooked += $numOfPersonInDorm;
				}
			}
		}

		// If there is no room that would be free for all the days, find the room that is the least booked, 
		// and add 'roomChanges' for the days that is booked, that is: it will find another room for the days where 
		// the mostly free room is booked.
		if($numOfBedsBooked < $numOfPerson) {
			$roomsNotToBookInReverse = $roomsNotToBook;
			// sort the $roomsNotToBook in the order of the number of dates unavailable (ascending)
			uasort($roomsNotToBook, "sortByArraySize");
			// sort the $roomsNotToBookInReverse in the order of the number of dates unavailable (descending)
			uasort($roomsNotToBookInReverse, "sortByArraySizeDesc");

			set_debug('getBookingData() - $roomsNotToBook = ' . print_r($roomsNotToBook, true));
			set_debug('getBookingData() - $roomsNotToBookInReverse = ' . print_r($roomsNotToBookInReverse, true));
			foreach($roomsNotToBook as $roomId => $datesUnavailable) {
				if($numOfBedsBooked == $numOfPerson) {
					break;
				}
				$personToBook = ($roomType['type'] == 'PRIVATE' ? $roomType['num_of_beds'] : $numOfPerson);
				$toBook[$roomId] = array('num_of_person' => $personToBook, 'type' => ($roomType['type'] == 'PRIVATE' ? 'ROOM' : 'BED'));
				$numOfBedsBooked += $personToBook;
				// add roomChanges for the dates unavailable
				foreach($datesUnavailable as $oneDate) {
					$changeRoomId = findRoomForDate($roomsNotToBookInReverse, $oneDate);
					if(is_null($changeRoomId)) {
						continue;
					}
					$roomChanges[$roomId][] = array('room_id' => $changeRoomId, 'date' => $oneDate);
					$roomsNotToBookInReverse[$changeRoomId][] = $oneDate;
					$roomsNotToBook[$changeRoomId][] = $oneDate;
					uasort($roomsNotToBook, "sortByArraySize");
					uasort($roomsNotToBookInReverse, "sortByArraySizeDesc");
				}
			}
		}
	}
	set_debug("getBookingData() - The toBook array's content: " . print_r($toBook, true));
	set_debug("getBookingData() - The roomChanges array's content: " . print_r($roomChanges, true));

	return array($toBook, $roomChanges);
}

function findRoomForDate($roomsNotToBookInReverse, $oneDate) {
	foreach($roomsNotToBookInReverse as $roomId => $datesUnavail) {
		if(!in_array($oneDate, $datesUnavail))
			return $roomId;
	}
	return null;
}


function sortByArraySize($arr1, $arr2) {
	if(count($arr1) < count($arr2)) return -1;
	elseif(count($arr1) > count($arr2)) return 1;
	else return 0;
}

function sortByArraySizeDesc($arr1, $arr2) {
	return -1 * sortByArraySize($arr1, $arr2);
}

function getNumOfNights($startDate, $endDate) {
	$sts = strtotime($startDate);
	$ets = strtotime($endDate);
	return round(($ets - $sts) / (60*60*24)) + 1;
}

function saveBookings($toBook, $roomChanges, $startDate, $endDate, &$rooms, &$roomTypes, &$specialOffers, $descriptionId, $link) {
	$startDate = str_replace('/', '-', $startDate);
	$endDate = str_replace('/', '-', $endDate);
	list($startYear, $startMonth, $startDay) = explode('-', $startDate);
	list($endYear, $endMonth, $endDay) = explode('-', $endDate);
	$startMonth = __getNormalizedDate($startMonth);
	$startDay = __getNormalizedDate($startDay);
	$endMonth = __getNormalizedDate($endMonth);
	$endDay = __getNormalizedDate($endDay);
	$startDate = "$startYear-$startMonth-$startDay";
	$endDate = "$endYear-$endMonth-$endDay";

	set_debug("saveBooking() - start: $startDate, end: $endDate");

	$bookingIds = array();
	$roomIdToBookingId = array();
	foreach($toBook as $roomId => $roomData) {
		$roomType = $roomTypes[$rooms[$roomId]['room_type_id']];
		$type = $roomData['type'];
		$numOfPerson = $roomData['num_of_person'];
		$payment = getPriceForInterval($startDate, $endDate, $type, $rooms[$roomId]);
		if($type == 'BED') {
			$payment = $payment * $numOfPerson;
		}

		$discount = 0;
		$selectedSo = null;
		foreach($specialOffers as $so) {
			if(specialOfferApplies($so, $roomType, getNumOfNights($startDate, $endDate), $startDate) and $so['discount_pct'] > $discount) {
				$discount = $so['discount_pct'];
				$selectedSo = $so;
			}
		}
		// apply special offer
		$specialOfferId = 'NULL';
		$discountedPayment = $payment;
		if($discount > 0) {
			$discountedPayment = $payment * (100 - $discount) / 100;
			$specialOfferId = $selectedSo['id'];
		}


		$time = date('Y-m-d H:i:s');
		$sql = "INSERT INTO bookings (booking_type, num_of_person, room_payment, room_id, description_id, creation_time, special_offer_id) VALUES ('$type', '$numOfPerson', '$discountedPayment', $roomId, $descriptionId, '$time', $specialOfferId)";
		set_debug($sql);

		if(!mysql_query($sql, $link)) {
			trigger_error("Cannot save booking: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
			set_error('Could not save booking for room: ' . $rooms[$roomId]['name']);
		} else {
			$id = mysql_insert_id($link);
			$roomIdToBookingId[$roomId] = $id;
			$bookingIds[] = $id;
		}
	}

	foreach($roomChanges as $roomId => $arr) {
		$bookingId = $roomIdToBookingId[$roomId];
		foreach($arr as $oneChange) {
			$dateOfRoomChange = str_replace('-', '/', $oneChange['date']);
			$rid = $oneChange['room_id'];
			$sql = "INSERT INTO booking_room_changes (booking_id, date_of_room_change, new_room_id) VALUES ($bookingId, '$dateOfRoomChange', $rid)";
			set_debug($sql);
			if(!mysql_query($sql, $link)) {
				trigger_error("Cannot save booking's room change: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
				set_error('Could not save booking\'s room change for room: ' . $rooms[$roomId]['name']);
			}
		}
	}

	return $bookingIds;
}

function __getNormalizedDate($dt) {
	if(strlen($dt) < 2) {
		$dt = '0' . $dt;
	}
	return $dt;
}



function cmpOrdNm($room1, $room2) {
	if($room1['_order'] < $room2['_order']) {
		return -1;
	} elseif($room1['_order'] > $room2['_order']) {
		return 1;
	} elseif($room1['name'] < $room2['name']) {
		return -1;
	} elseif($room1['name'] > $room2['name']) {
		return 1;
	}
	return 0;
}


?>