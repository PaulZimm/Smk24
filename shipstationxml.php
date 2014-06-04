<?php
/**
 * GF2ShipStationXML for Smk24.com Groupon WP GravityForms Entries
 * Gravity Forms API to ShipStation XML [Copyright R&Y Group 2014]
 * 
 * Groupon Orders to ShipStation XML 
 * Usage:  http://www.smk24.com/shipstationtest.php?debug=1
 * https://www.smk24.com/shipstationxml.php?action=export&start_date=6/01/2014&end_date=6/05/2014
 * 
 * @version	GF2ShipStationXML 1.0.3
 * @author	Paul Zimm <paulzimm@gmail.com>
 * 
 * @param	string	$action			This value will always be “export” when ShipStation is requesting order information. 
 * @param	date    $start_date		The start date in UTC time format: MM/dd/yyyy HH:mm (24 hour notation). For example: 03/23/2012 21:09 
 * @param	date	$end_date		The end date in UTC time (same format as start_date). 
 * 
 * @param	int     $_count			Internal XML counter
 * @return	string  $_return		PHP "return type hinting"
 * @notes	string	$_phpdoc		phpdoc -d . -t zimm_classes
*/

	// namespace PaulZimm;
	ini_set('memory_limit', '-1');

	/* ************************** */
	/* SET ACTIVE GRAVITYFORM IDs */
	/* ************************** */

	$force_start_date = '2014-06-01';
	$force_end_date   = '2014-06-02';
	$form_ids = array('5','12','13','18','26','29','32','34','36','39','41','42','43','44','45','47','48','49'); # Hard coded for now

	/** Setup the WordPress Environment **/
	require(dirname(__FILE__).'/wp-config.php');
	require(dirname(__FILE__).'/wp-load.php');
	# require(dirname(__FILE__).'/wp-includes/wp-db.php');

	/* ******************************************** */
	/* DROP AND REBUILD PRODUCT ITEM NAMES AND SKUs */
	/* ******************************************** */

	$count = 0;
	// $wpdb->query('DELETE FROM shop_pz_items WHERE id > 0');
	foreach ($form_ids as $form_id) {
		$result = $wpdb->get_row("SELECT display_meta FROM shop_rg_form_meta WHERE form_id='$form_id'", ARRAY_A);
		$data = json_decode($result['display_meta'], true); # Decode WordPress form layout into an Array
		if (!$data) @mail('paul@zimmtech.net', $_SERVER['REQUEST_URI'], "JSON Error on 'shop_rg_form_meta' WHERE form_id='$form_id'", 'From: admin@smk24.com');
		for ($key=4; $key<40; $key++) {
			$sku = trim($data['fields'][$key]['adminLabel']);
			if ((strlen($sku) >= 3) && ($sku!='Yes 18') && ($sku!='I am over 18')) {
				$count++;
				$item_sku = addslashes(trim($data['fields'][$key]['adminLabel']));
				$item_name = addslashes(trim($data['fields'][$key]['label']));
				// $wpdb->query("INSERT INTO shop_pz_items SET id='$count', form_id='$form_id', item_sku='$item_sku', item_name='$item_name'");
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
	$daysAgo = 3; // Gets X number days of orders [Start Date]
	$nextDay = 1; // Gets 1 day past today of orders [End Date]
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
		$message .= str_replace('Array', 'XML Visitor Request', print_r($_GET, true));
		$message .= "\nSent from: http://smk24.com/shipstationxml.php?debug=1";

		$subject = 'ShipStationXML.php';
		$headers = 'From: Smk24 Admin <admin@smk24.com>' . "\r\n";
		// @mail('paul@zimmtech.com', $subject, $message, $headers);
		// @mail('ron@rygrp.com', $subject, $message, $headers);
	}

	/** DEBUG VIEWER **/
	/* www.smk24.com/shipstationxml.php?debug=1 */
	if ($_GET['debug']) {
		print "<pre><strong style='color:red'>0".$daysAgo."</strong> Days of Orders [From: ".$search_criteria["start_date"]."]\n\n";
		print "<b>Total count: ".count($items)."</b>\n\n";
		echo str_replace('Array', 'Form_IDs', print_r($form_ids, true));
		print_r($items);
		exit(); // Halt!
	}


	/* ********************************** */
	/* START LOOP - PRINT SHIPSTATION XML */
	/* ********************************** */

	function itemsXML($sku, $item, $price, $quantity) {
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

	header('Content-type: application/xml');
	echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	echo "<Orders>\n";

	foreach ($items as $item):

		unset($data); // Format data to be saved

		# GF API Date Example: [date_created] => 2014-04-15 16:07:21
		$date_array = explode(' ', trim($item['date_created']));
		$thedate = $date_array[0];
		$thetime = $date_array[1];
		$date_array = explode('-', $thedate);
		$theyear  = $date_array[0];
		$themonth = $date_array[1];
		$theday   = $date_array[2];
		# XML Required Format: MM/dd/yyyy HH:mm (24 hour notation)
		$date_created = $themonth.'/'.$theday.'/'.$theyear.' '.$thetime;

		$data['FormID'] = trim($item['form_id']);			# Ex: Gravity Form ID
		$data['OrderItemId'] = trim($item['8']);			# Ex: KAESQTFF
		$data['OrderId'] = trim($item['id']);				# Ex: 71574
		$data['OrderDate'] = trim($date_created);			# Ex: 5/30/2012 21:00:01
		$data['ExportDate'] = date('n/d/Y H:i');			# Ex: 6/01/2014 22:00
		$data['Email'] = trim($item['4']);					# Ex: paul@zimmtech.net
		$ship_array = explode('|', trim($item['16']));		# Ex: economy|0
		$data['ShippingClass'] = trim($ship_array[0]);		# Ex: standard|5
		$data['ShippingCost']  = trim($ship_array[1]);		# Ex: expedited|10
		$price = trim(str_replace('$', '', $item['5.2']));	# Ex: $29.99
		$data['OrderTotal'] = $price;
		$data['UnitPrice'] = $price;

		$data['ShipToName'] = trim(ucwords($item['1.3'].' '.$item['1.6']));
		$data['ShipToStreet1'] = trim($item['2.1']);
		$data['ShipToStreet2'] = trim($item['2.2']);
		$data['ShipToCity'] = trim($item['2.3']);
		$data['ShipToState'] = trim($item['2.4']);
		$data['ShipToZipCode'] = str_pad(trim($item['2.5']), 5, '0', STR_PAD_LEFT);
		$data['ShipToCountry'] = 'US'; # Ex: United States
		$data['BillToPhone'] = trim($item['3']);
		$data['BillToName'] = trim(ucwords($item['1.3'].' '.$item['1.6']));
		$data['BillToFirstName'] = trim(ucwords($item['1.3']));
		$data['BillToLastName'] = trim(ucwords($item['1.6']));
		$data['CustomerNotes'] = trim($item['source_url']);
		$data['InternalNotes'] = 'Transaction# '.trim($item['transaction_id']);

		// In development!!
		$data['SKU'] = trim($item['5.1']);
		$data['ItemName'] = trim($item['5.1']);
		$data['Quantity'] = trim($item['5.3']);

		// XML Sanity checks below...
		if (!is_numeric($data['OrderTotal'])) { $data['OrderTotal'] = '0'; }
		if (!is_numeric($data['ShippingCost'])) { $data['ShippingCost'] = '0'; }

		$items_list = itemsXML($data['SKU'], $data['ItemName'], $data['UnitPrice'], $data['Quantity']);

		for ($i=35; $i<=125; $i++) {
			if (($i==55) || ($i==56) || ($i==57)) {
				// Skip these keys (Address info)
			} else {
				$keyQty = $i.'.3';
				$itemQty = trim($item[$keyQty]);
				if (is_numeric($itemQty)) {
					$keyName = $i.'.1';
					$itemName = trim($item[$keyName]);

					$keyPrice = $i.'.2';
					$itemPrice = trim(str_replace('$', '', $item[$keyPrice]));

					// Get Item SKU by Product Name
					$result = $wpdb->get_row("SELECT item_sku FROM shop_pz_items WHERE form_id='$data[FormID]' AND item_name='$itemName'", ARRAY_A);
					$itemSKU = $result['item_sku'];

					if (!$itemSKU) { $itemSKU = $itemName; }
					$items_list .= itemsXML($itemSKU, $itemName, $itemPrice, $itemQty);
				}
			}
		}


/* PRINT SHIPSTATION XML LOOP */
?>
  <Order>
    <OrderID><?=$data['OrderId']?></OrderID>
    <OrderNumber><?=$data['OrderItemId']?></OrderNumber>
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
