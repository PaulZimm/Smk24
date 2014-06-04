Smk24
=====

Smk24.com

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
