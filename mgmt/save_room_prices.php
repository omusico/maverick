<?php

require("includes.php");
require(RECEPCIO_BASE_DIR . "room_booking.php");
require(ADMIN_BASE_DIR . "common_booking.php");

if(!checkLogin(SITE_MGMT)) {
	return;
}


$link = db_connect();

if(isset($_REQUEST['room_type_ids']) and is_array($_REQUEST['room_type_ids'])) {
	$roomTypeIds = $_REQUEST['room_type_ids'];
} else {
	$roomTypeIds = array($_REQUEST['room_type_id']);
}
$_SESSION['room_price_room_type_ids'] = $roomTypeIds;
$sql = "select * from room_types where id IN (" . implode(",", $roomTypeIds) . ")";
$result = mysql_query($sql, $link);
if(!$result) {
	trigger_error("Cannot get room type in admin interface: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
	set_error("Cannot save price: cannot get room type");
	mysql_close($link);
	header("Location: " . $_SERVER['HTTP_REFERER']);
	return;
}

$roomTypes = array();
while($row = mysql_fetch_assoc($result)) {
	$roomTypes[$row['id']] = $row;
}

$startDate = $_REQUEST['start_date'];
$endDate = $_REQUEST['end_date'];

if(isset($_REQUEST['sync'])) {
	header('Location: ' . RECEPCIO_BASE_URL . "synchro/main.php?start_date=$startDate&end_date=$endDate&sites[]=myallocator&login_hotel=" . $_SESSION['login_hotel']);
} else {
	header("Location: " . $_SERVER['HTTP_REFERER']);
}

$_SESSION['room_price_start_date'] = $startDate;
$_SESSION['room_price_end_date'] = $endDate;
if(isset($_REQUEST['days'])) {
	$days = $_REQUEST['days'];
} else {
	$days = array(1,2,3,4,5,6,7);
}

$_SESSION['room_price_days'] = $days;
list($startYear,$startMonth,$startDay) = explode('-', $startDate);
list($endYear,$endMonth,$endDay) = explode('-', $endDate);

$startDateDash = str_replace('-', '/', $startDate);
$endDateDash = str_replace('-', '/', $endDate);

$todaySlash = date('Y/m/d');
$roomTypes = loadRoomTypesWithAvailableBeds($link, $startDate, $endDate);
$rooms = loadRooms($startYear, $startMonth, $startDay, $endYear, $endMonth, $endDay, $link);
$sql = "SELECT * FROM prices_for_date WHERE room_type_id in (" . implode(',', $roomTypeIds) . ") AND date>='$startDateDash' AND date<='$endDateDash'";
$result = mysql_query($sql, $link);
if(!$result) {
	trigger_error("Cannot get old room prices in admin interface: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
}

$priceRows = array();
while($row = mysql_fetch_assoc($result)) {
	if(!isset($priceRows[$row['room_type_id']])) {
		$priceRows[$row['room_type_id']] = array();
	}
	$priceRows[$row['room_type_id']][$row['date']] = $row;
}

$newPriceMessage = '';
$historyMessage = '';
$historyValues = array();
$newPriceValues = array();
$priceSettingSkipped = array();
foreach($roomTypeIds as $roomTypeId) {
	$roomType = $roomTypes[$roomTypeId];
	for($currDate = $startDate; $currDate <= $endDate; $currDate = date('Y-m-d', strtotime($currDate . ' +1 day'))) {
		$currDayOfWeek = date('N', strtotime($currDate));
		if(!in_array($currDayOfWeek, $days)) {
			if(!in_array($currDate, $priceSettingSkipped)) {
				$priceSettingSkipped[] =  $currDate;
			}
			continue;
		}
		$dateStr = str_replace('-', '/', $currDate);
		if(isset($_REQUEST['price'])) {
			$val = intval($_REQUEST['price']);
			$spb = intval($_REQUEST['surcharge_per_bed']);
		} else {
			$val = intval($_REQUEST[$dateStr]);
			if(isset($_REQUEST['spb_' . $dateStr])) {
				$spb = intval($_REQUEST['spb_' . $dateStr]);
			} else {
				$spb = 0;
			}
		}
		if($val > 0) {
			if(isset($priceRows[$roomTypeId][$dateStr])) {
				$bookings = getBookings($roomTypeId, $rooms, $currDate, $currDate);
				$avgNumOfBeds = getAvgNumOfBedsOccupied($bookings, $currDate, $currDate);
				$occupancy = round($avgNumOfBeds / $roomTypes[$roomTypeId]['available_beds'] * 100);
				$priceRow = $priceRows[$roomTypeId][$dateStr];
	
				$newPricePerRoom = ((isPrivate($roomType) or isApartment($roomType)) ? $val : null);
				$newPricePerBed = (isDorm($roomType) ? $val : null);
				if(isSamePrice($newPricePerRoom, $newPricePerBed, $spb, $priceRow)) {
					continue;
				}

				$historyValues[] = "('" . $priceRow['date'] . "', " .
						(is_null($priceRow['price_per_room']) ? 'NULL' : $priceRow['price_per_room']) . ", " .
						(is_null($priceRow['price_per_bed']) ? 'NULL' : $priceRow['price_per_bed']) . ", " .
						$roomTypeId . ", " .
						(is_null($priceRow['price_set_date']) ? 'NULL' : '\''.$priceRow['price_set_date'].'\'') . ", " .
						"'$todaySlash', $occupancy, " .
						(is_null($priceRow['surcharge_per_bed']) ? 'NULL' : '\''.$priceRow['surcharge_per_bed'].'\'') . ")";
				$historyMessage .= "New history item: " . $roomType['name'] . " " . $priceRow['date'] . "<br>\n";
			}

			$sql = "DELETE FROM prices_for_date WHERE room_type_id=$roomTypeId AND date='$dateStr'";
			$result = mysql_query($sql, $link);
			if(!$result) {
				trigger_error("Cannot delete old room prices in admin interface: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
			}
			
			// For private and apartment set the room price, for dorm set the bed price.
			$newPriceValues[] = "($roomTypeId, '$dateStr', " . ($roomType['type'] != 'DORM' ? $val : 'NULL') . ", " . ($roomType['type'] == 'DORM' ? $val : 'NULL') . ", '$todaySlash', $spb)";
			$newPriceMessage .= "New price saved: " . $roomType['name'] . " $dateStr <br>\n";
		}
	}
}


set_message("Price setting skipped for dates: " . implode(',', $priceSettingSkipped));

if(count($historyValues) > 0) {
	$sql = "INSERT INTO prices_for_date_history (date, price_per_room, price_per_bed, room_type_id, price_set_date, price_unset_date, occupancy, surcharge_per_bed) VALUES " . implode(', ', $historyValues);
	$result = mysql_query($sql, $link);
	if(!$result) {
		trigger_error("Cannot create room prices history in admin interface: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
	} else {
		set_message('price history saved (' . count($historyValues) . ' rows inserted)');
	}
} else {
	set_message('No historical data saved');
}

if(count($newPriceValues) > 0) {
	$sql = "INSERT INTO prices_for_date (room_type_id, date, price_per_room, price_per_bed, price_set_date, surcharge_per_bed) VALUES " . implode(', ', $newPriceValues);
	$result = mysql_query($sql, $link);
	if(!$result) {
		trigger_error("Cannot create new room prices in admin interface: " . mysql_error($link) . " (SQL: $sql)", E_USER_ERROR);
	} else {
		set_message('new prices saved (' . count($newPriceValues) . ' rows inserted)');
	}
} else {
	set_message('No price data saved');
}

set_message($newPriceMessage);
set_message($historyMessage);

mysql_close($link);


function isSamePrice($newPricePerRoom, $newPricePerBed, $spb, $priceRow) {
	if(!is_null($newPricePerRoom) and ($newPricePerRoom != $priceRow['price_per_room'])) {
		return false;
	}
	if(!is_null($newPricePerBed) and ($newPricePerBed != $priceRow['price_per_bed'])) {
		return false;
	}
	if(!is_null($spb) and ($dpb != $priceRow['surcharge_per_bed'])) {
		return false;
	}
	return true;
}

?>
