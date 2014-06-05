<?php
/**
 * GF2ShipStationXML for Smk24.com Groupon Redemptions [WORDPRESS]
 * Gravity Forms API to ShipStation XML © Copyright R&Y Group 2014
 * 
 * Groupon Orders to ShipStation XML 
 * Usage:  http://www.smk24.com/shipstationtest.php?debug=1
 * https://www.smk24.com/shipstationxml.php?action=export&start_date=6/01/2014&end_date=6/05/2014
 * 
 * @version	GF2ShipStationXML 1.0.4
 * @author	Paul Zimm <paulzimm@gmail.com>
 * 
 * @param	string	$action			This value will always be “export” when ShipStation is requesting order information.
 * @param	date    $start_date		The start date in UTC time format: MM/dd/yyyy HH:mm (24 hour notation). Example: 03/23/2012 21:09
 * @param	date	$end_date		The end date in UTC time (same format as start_date).
 * 
 * @param	int     $_count			Internal XML counter
 * @return	string  $_return		PHP "return type hinting"
 * @notes	string	$_phpdoc		phpdoc -d . -t zimm_classes
*/

	// namespace PaulZimm;
	ini_set('memory_limit', '-1'); # Needed :)

	/* ************************** */
	/* SET ACTIVE GRAVITYFORM IDs */
	/* ************************** */

	$form_ids = array('5','12','13','18','26','29','32','34','36','39','41','42','43','44','45','47','48','49'); # Hard coded for now
	# $force_start_date = '2014-06-01';
	# $force_end_date   = '2014-06-05';

	/** Setup the WordPress Environment **/
	require(dirname(__FILE__).'/wp-config.php');
	require(dirname(__FILE__).'/wp-load.php');
	# require(dirname(__FILE__).'/wp-includes/wp-db.php');


	/* ******************************************** */
	/* DROP AND REBUILD PRODUCT ITEM NAMES AND SKUs */
	/* ******************************************** */

	$_count = 0;
	// $wpdb->query('DELETE FROM shop_pz_items WHERE id > 0');
	foreach ($form_ids as $form_id) {
		$result = $wpdb->get_row("SELECT display_meta FROM shop_rg_form_meta WHERE form_id='$form_id'", ARRAY_A);
		$data = json_decode($result['display_meta'], true); # Decode WordPress form layout into an Array
		# if (!$data) @mail('paul@zimmtech.net', $_SERVER['REQUEST_URI'], "JSON Error on 'shop_rg_form_meta' WHERE form_id='$form_id'", 'From: admin@smk24.com');
		for ($key=4; $key<40; $key++) {
			$sku = trim($data['fields'][$key]['adminLabel']);
			if ((strlen($sku) >= 3) && ($sku!='Yes 18') && ($sku!='I am over 18')) {
				$_count++;
				$item_sku = addslashes(trim($data['fields'][$key]['adminLabel']));
				$item_name = addslashes(trim($data['fields'][$key]['label']));
				// $wpdb->query("INSERT INTO shop_pz_items SET id='$_count', form_id='$form_id', item_sku='$item_sku', item_name='$item_name'");
			}
		}
	}


	/* ********************************************* */
	/* GRAVITY FORMS API $gf->get_entries($form_ids) */
	/* ********************************************* */

	// GFAPI::get_form($obj);
	$gf = new GFAPI();

	// Defaults for $gf->get_entries()
	$sorting = array();
	$search_criteria = array();
	$paging = array('offset' => 0, 'page_size' => 99999);
	$daysAgo = 1; // Gets X number days of orders [Start Date]
	$nextDay = 1; // Gets 1 day past today of order [End Date]
	$prevWeek = time() - ($daysAgo * 24 * 60 * 60);
	$nextDay  = time() + ($nextDay * 24 * 60 * 60);

	if ($force_start_date || $force_end_date) {
		$search_criteria["start_date"] = $force_start_date;
		$search_criteria["end_date"]   = $force_end_date;
	} else {
		$search_criteria["start_date"] =  date('Y-m-d', $prevWeek); # X number days of orders
		$search_criteria["end_date"]   =  date('Y-m-d', $nextDay);  # Tomorrow Ex: 2014-06-30
	}

	/** EXECUTE GRAVITY FORMS API **/
	/* /wp-content/plugins/gravityforms/includes/api.php */
	if (is_numeric($_GET['id'])) {
		$items = $gf->get_entry($_GET['id']); # Gravity Forms get single form entry
	} else {
		/** SPEC $gf->get_entries($form_ids, $search_criteria = array(), $sorting = null, $paging = null, &$total_count = null) **/
		$items = $gf->get_entries($form_ids, $search_criteria, $sorting, $paging);
	}

	/** EMAIL OUTPUT **/
	if ($_GET) {
		$visitorip = getenv('REMOTE_ADDR');
		$hostname  = gethostbyaddr($visitorip);
		$message  = "IP Address: $visitorip \n";
		$message .= "Hostname:  $hostname \n\n";
		$message .= "Dates from: $search_criteria[start_date] to $search_criteria[end_date] \n\n";
		$message .= str_replace('Array', 'Visitor Requested:', print_r($_GET, true));
		$message .= "\nSent from: http://smk24.com/shipstationxml.php?debug=1";
		$subject = 'shipstationxml.php';
		$headers = 'From: Smk24 Admin <admin@smk24.com>' . "\r\n";
		@mail('paul@zimmtech.com', $subject, $message, $headers);
		@mail('zmaster@bellsouth.net', $subject, $message, $headers);
		// @mail('ron@rygrp.com', $subject, $message, $headers);
	}

	/** DEBUG VIEWER **/
	/* www.smk24.com/shipstationxml.php?debug=1 */
	if ($_GET['debug']) {
		print "<pre> Dates from: $search_criteria[start_date] to $search_criteria[end_date] \n\n";
		print "<b>Total count: ".count($items)."</b> \n\n";
		echo str_replace('Array', 'Form_IDs', print_r($form_ids, true));
		print_r($items);
		exit(); // Halt!
	}


	/* ********************************** */
	/* START LOOP - PRINT SHIPSTATION XML */
	/* ********************************** */

	header('Content-type: application/xml');
	echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	echo "<Orders>\n";

	function itemsXML($sku, $item, $quantity, $price) {
		if (!trim($sku)) { $sku = 'Item SKU'; }
		if (!trim($item)) { $item = 'Product Name'; }
		if (!is_numeric($price)) { $price = '0'; }
		if (!is_numeric($quantity)) { $quantity = '1'; }
		$str  = '      <Item>'."\n";
		$str .= '        <SKU><![CDATA['.$sku.']]></SKU>'."\n";
		$str .= '        <Name><![CDATA['.$item.']]></Name>'."\n";
		$str .= '        <UnitPrice>'.$price.'</UnitPrice>'."\n";
		$str .= '        <Quantity>'.$quantity.'</Quantity>'."\n";
		$str .= '      </Item>'."\n";
		return $str;
	}

	foreach ($items as $item):

		unset($data); // Entry data to be exported
		unset($items_list);  // XML Items list for addons
		$OilOrHooka = false; // Exception for these types

		# GF API Date Example: [date_created] => 2014-06-01 18:10:09
		# XML Required Format: m/dd/yyyy hh:mm (24 hour notation)
		$order_date = date('n/d/Y H:i', strtotime(trim($item['date_created'])));

		$data['FormID'] = trim($item['form_id']);			# Ex: Gravity Form ID
		$data['Groupon'] = trim($item['8']);				# Ex: KAESQTFF
		$data['OrderId'] = trim($item['id']);				# Ex: 71574
		$data['OrderDate'] = trim($order_date);				# Ex: 5/30/2013 23:00
		$data['ExportDate'] = date('n/d/Y H:i');			# Ex: 6/01/2014 01:01
		$data['Email'] = trim($item['4']);					# Ex: paul@zimmtech.net

		$ship_array = explode('|', trim($item['16']));		# Ex: economy|0
		$data['ShippingClass'] = trim($ship_array[0]);		# Ex: standard|5
		$data['ShippingCost']  = trim($ship_array[1]);		# Ex: expedited|10
		if (!is_numeric($data['ShippingCost'])) { $data['ShippingCost'] = '0'; }

		$data['ShipToName'] = ucwords(trim($item['1.3'].' '.$item['1.6']));
		$data['ShipToStreet1'] = ucwords(trim($item['2.1']));
		$data['ShipToStreet2'] = ucwords(trim($item['2.2']));
		$data['ShipToCity'] = ucwords(trim($item['2.3']));
		$data['ShipToState'] = trim($item['2.4']);
		$data['ShipToZipCode'] = str_pad(trim($item['2.5']), 5, '0', STR_PAD_LEFT);
		$data['ShipToCountry'] = 'US'; # Ex: United States
		$data['BillToPhone'] = trim($item['3']);
		$data['BillToName'] = ucwords(trim($item['1.3'].' '.$item['1.6']));
		$data['BillToFirstName'] = ucwords(trim($item['1.3']));
		$data['BillToLastName'] = ucwords(trim($item['1.6']));
		$data['CustomerNotes'] = str_replace('https://', '', trim($item['source_url']));
		$data['InternalNotes'] = 'Transaction# '.trim($item['transaction_id']);

		// 34->Oil Bundle 5pk, 36->Oil Bundle 10pk
		if (($data['FormID']=='34') || ($data['FormID']=='36')) {
			# Do something...
		}

		// www.smk24.com/ehookah5pkgroupon/
		// www.smk24.com/ehookah10pkgroupon/
		// 47->Ehookah 10pk, 48->Ehookah 5pk
		if (($data['FormID']=='47') || ($data['FormID']=='48')) {
			$OilOrHooka = true;
			unset($hookas); # Ex: EH700-MANGO, EH700-APPLE
			$hookas[] = trim(stristr($item['79'], '|', 1).'-'.stristr( $item['5'], '|', 1));
			$hookas[] = trim(stristr($item['80'], '|', 1).'-'.stristr($item['77'], '|', 1));
			$hookas[] = trim(stristr($item['81'], '|', 1).'-'.stristr($item['76'], '|', 1));
			$hookas[] = trim(stristr($item['82'], '|', 1).'-'.stristr($item['75'], '|', 1));
			$hookas[] = trim(stristr($item['83'], '|', 1).'-'.stristr($item['78'], '|', 1));
			// 47->Ehookah 10pk
			if ($data['FormID']=='47') {
				$hookas[] = trim(stristr($item['85'], '|', 1).'-'.stristr($item['84'], '|', 1));
				$hookas[] = trim(stristr($item['87'], '|', 1).'-'.stristr($item['86'], '|', 1));
				$hookas[] = trim(stristr($item['91'], '|', 1).'-'.stristr($item['88'], '|', 1));
				$hookas[] = trim(stristr($item['92'], '|', 1).'-'.stristr($item['89'], '|', 1));
				$hookas[] = trim(stristr($item['93'], '|', 1).'-'.stristr($item['90'], '|', 1));
			}
			foreach ($hookas as $hooka) {
				/** Create XML items list for Ehookas **/
				$items_list .= itemsXML($hooka, $hooka, 1, 0);
			}
		}

		/** Get main Groupon product information including item name, SKU and price **/
		if (!$OilOrHooka) {
			// DISABLED for Oil Bundles and Ehooka Groupons
			// Get META content from DB by Groupon Code and Gravity Form ID
			$result = $wpdb->get_row("SELECT meta FROM shop_rg_coupons WHERE form_id='".$data['FormID']."' AND meta LIKE '%".$data['Groupon']."%'", ARRAY_A);
			if (!$result) @mail('paul@zimmtech.net', 'ShipStationXML?code='.$data['Groupon'].'&form='.$data['FormID'], 'MySQL Error finding Groupon Code!', 'From: admin@smk24.com');
			$meta = explode('"', $result['meta']);
			$coupon_name = trim($meta[3]); # Ex: Atmos Optimus Black
			$coupon_code = trim($meta[7]); # Ex: DWTXTWKW
			$coupon_cost = trim(str_replace('$', '', $meta[15]));
			if ($coupon_name) {
				// Get first Groupon Item SKU from DB by Product Name
				$result = $wpdb->get_row("SELECT item_sku FROM shop_pz_groupons WHERE coupon_name='$coupon_name'", ARRAY_A);
				$data['SKU'] = trim($result['item_sku']);
				$data['ItemName'] = $coupon_name;
			} else {
				$data['SKU'] = trim($item['5.1']);
				$data['ItemName'] = trim($item['5.1']);
			}
			$data['Quantity'] = trim($item['5.3']);
			/** Create XML item for first/main item from Groupon **/
			$items_list .= itemsXML($data['SKU'], $data['ItemName'], $data['Quantity'], $coupon_cost);
		}

		/** Now Loop through all possible AddOn/Upsale items available **/
		unset($data['OrderTotal']);
		for ($i=35; $i<=125; $i++) {
			if (($i==55) || ($i==56) || ($i==57)) {
				// Skip these keys (Address info)
			} else {
				$keyQty = $i.'.3';
				$itemQty = trim($item[$keyQty]);
				if (is_numeric($itemQty) && ($itemQty > 0)) {
					$keyName = $i.'.1';
					$itemName = trim($item[$keyName]);
					$keyPrice = $i.'.2';
					$itemPrice = trim(str_replace('$', '', $item[$keyPrice]));
					$data['OrderTotal'] += $itemPrice;

					// Get Item SKU from DB by Product Name
					$result = $wpdb->get_row("SELECT item_sku FROM shop_pz_items WHERE form_id='$data[FormID]' AND item_name='$itemName'", ARRAY_A);
					$itemSKU = $result['item_sku'];
					if (!$itemSKU) {
						$itemArray = explode(' (', $itemName);
						$itemSKU = $itemArray[0];
					}
					/** Create XML items list for any AddOn/UpSale items **/
					$items_list .= itemsXML($itemSKU, $itemName, $itemQty, $itemPrice);
				}
			}
		}
		if (is_numeric($data['OrderTotal'])) {
			$data['OrderTotal'] = number_format($data['OrderTotal'], 2);
		} else {
			$data['OrderTotal'] = '0';
		}

/* PRINT SHIPSTATION XML LOOP */
?>
  <Order>
    <OrderID><?=$data['OrderId']?></OrderID>
    <OrderNumber><?=$data['Groupon']?></OrderNumber>
    <OrderDate><?=$data['OrderDate']?></OrderDate>
    <LastModified><?=$data['ExportDate']?></LastModified>
    <OrderStatus>paid</OrderStatus>
    <OrderTotal><?=$data['OrderTotal']?></OrderTotal>
    <ShippingMethod><?=$data['ShippingClass']?></ShippingMethod>
    <ShippingAmount><?=$data['ShippingCost']?></ShippingAmount>
    <CustomerNotes><![CDATA[<?=$data['CustomerNotes']?>]]></CustomerNotes>
    <InternalNotes><![CDATA[<?=$data['InternalNotes']?>]]></InternalNotes>
    <Customer>
      <CustomerCode><?=$data['OrderId']?></CustomerCode>
      <BillTo>
        <Name><![CDATA[<?=$data['BillToName']?>]]></Name>
        <Phone><![CDATA[<?=$data['BillToPhone']?>]]></Phone>
        <Email><![CDATA[<?=$data['Email']?>]]></Email>
      </BillTo>
      <ShipTo>
        <Name><![CDATA[<?=$data['ShipToName']?>]]></Name>
        <Address1><![CDATA[<?=$data['ShipToStreet1']?>]]></Address1>
        <Address2><![CDATA[<?=$data['ShipToStreet2']?>]]></Address2>
        <City><![CDATA[<?=$data['ShipToCity']?>]]></City>
        <State><![CDATA[<?=$data['ShipToState']?>]]></State>
        <PostalCode><![CDATA[<?=$data['ShipToZipCode']?>]]></PostalCode>
        <Country><![CDATA[<?=$data['ShipToCountry']?>]]></Country>
      </ShipTo>
    </Customer>
    <Items>
<?=$items_list?>
    </Items>
  </Order>
<?php
	endforeach;
	echo "</Orders>\n";
	/* END LOOP - PRINT SHIPSTATION XML */
?>
