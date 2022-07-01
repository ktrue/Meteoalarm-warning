<?php
#Get regional alarm/warning from https:#www.meteoalarm.eu/
# Script by Ken True - saratoga-weather.org
# Version 1.00 - 13-Sep-2008 - Initial Release
# Version 1.01 - 20-Sep-2008 - removed TZ setting (not used), added $EUAtarget option for links
# Version 1.02 - 06-Oct-2008 - some XHTML 1.0-Strict changes for <br />
# Version 2.00 - 22-Apr-2010 - major changes to support major meteoalarm.eu website changes
# Version 2.01 - 23-Apr-2010 - changes for revised TOS of meteoalarm.eu website
# Version 2.02 - 26-Jan-2011 - added support for global $cacheFileDir for new templates
# Version 2.03 - 07-Feb-2011 - added color based on warning level
# Version 2.04 - 24-Feb-2011 - added UTF-8->template character set conversion
# Version 2.05 - 02-Mar-2011 - added slovenian (lang=si) capability
# Version 2.06 - 05-Jun-2011 - changes for updated meteoalarm.eu website
# Version 2.07 - 22-Oct-2011 - added more UTF-8->character set conversion for 'more info' area
# Version 2.08 - 18-Jul-2012 - added fixes for meteoalarm.eu website changes
# Version 2.09 - 17-Aug-2016 - added czech (lang=cs) capability+fix color decode
# Version 2.10 - 22-Jul-2018 - switched to cURL and https for access to www.meteoalarm.eu
#
#Get regional alarm/warning from https://www.meteoalarm.org/  2022
# NOTE: with version 3.00, the return coding is all in UTF-8 characters.
#    the Saratoga templates retuire 
#     $useUTF8 = true;
#  after the $TITLE entry in any wx...php page the output is used.
#  output is now placed in two files:
#  $warn_details file for the FULL warning display (used in wxadvisory.php
#  $warn_summary file for the summary warning display (used in the top of wxindex.php
# 
# Version 3.00 - 12-May-2022 - initial release using meteoalarm.org data
# Version 3.01 - 14-May-2022 - fixed some Notice errata, XHTML formatting, added NUTSn-EMMA checking
# Version 3.10 - 18-May-2022 - added summary alerts for display, reformatted outputs
# Version 3.11 - 19-May-2022 - add $minAlertLevel / $SITE['EUAminLevel'] feature to control display of alerts
# Version 3.12 - 20-Jun-2022 - compact the meteoalarm-summary.html table
# Version 3.13 - 21-Jun-2022 - center and align alert summary to ajax-dashboard in wide/narrow aspect
# Version 3.14 - 01-Jul-2022 - fix for partial display of alert summary/detail boxes for alerts < $minAlertLevel
#
# error_reporting(E_ALL); # uncomment for error checking
#
# this script is designed to be used by
#
#   include("get-meteoalarm-warning-inc.php");
#
# in a Saratoga World template.
#-------------------------------------------------------------------------------------------------
#  Original script wrnWarningEU-CAP.php|01|2021-06-03|  # version 2  ATOM feed # release 2012_lts
#   is from the PWS-Dashboard
#   author:  Wim van der Kuil https://pwsdashboard.com/
#
# Adapted (with permission) by Ken True - saratoga-weather.org
# for use with Saratoga Template set (Base-World)
#
# Used with Wim van der Kuil's permission - May, 2022
#
#-------------------------------------------------------------------------------------------------
# Configuration:
#
# Find the warning area for your location by going https:#saratoga-weather.org/meteoalarm-map/
# Navigate the map by using the search function or zoom/scroll to your location.
# The mouse cursor will display the EMMA_ID of that area.  Write that EMMA_ID down.<br />
# Repeat the process for adjacent areas if need be.
#
# For Saratoga template use, add to your Settings.php:
#
# $SITE['EUwarnings'] = '<EMMA_ID>';
#
# where the <EMMA_ID> is replaced by the EMMA_ID(s) you found above.  Separate multiple
# EMMA_IDs by a comma (,) (e.g. 'DK002,DK004,DK005';
# No other customization is needed in the script for use in the Saratpga template.
#
# You must also include in Settings.php
#
# $SITE['useMeteoalarm'] = true;
#
# to activate the displays in both wxindex.php and wxadvisory.php
#-------------------------------------------------------------------------------------------------

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view') {
  $filenameReal = __FILE__;
  $download_size = filesize($filenameReal);
  header('Pragma: public');
  header('Cache-Control: private');
  header('Cache-Control: no-cache, must-revalidate');
  header('Content-type: text/plain; charset=UTF-8');
  header('Accept-Ranges: bytes');
  header("Content-Length: $download_size");
  header('Connection: close');
  readfile($filenameReal);
  exit;
}
#-------------------------------------------------------------------------------------------------
# local default settings .. overridden by Settings.php entries
#-------------------------------------------------------------------------------------------------
#$alarm_area = 'DK002';  # leave unset-- the $SITE['EUwarnings'] will configure it.
$cacheFileDir = './';
$ourTZ = 'Europe/Brussels';
$dateFormat = "Y-m-d";
$timeFormatShort = "h:i T";
$minAlertLevel = 2; # 1=green, 2=yellow, 3=orange, 4=red - warnings below this will be suppressed.
# end local settings
#-------------------------------------------------------------------------------------------------
# end of configurable settings
#-------------------------------------------------------------------------------------------------
#
# please don't change these
$EUAversion = 'get-meteoalarm-warning-inc.php - V3.14 - 01-Jul-2022';
$cache_max_age = 300;
$detail_page = true;
$detail_page_url = './wxadvisory.php';
$warn_cache   = 'meteoalarm.arr';             // will be prepended with $cacheFileDir
$warn_summary = 'meteoalarm-summary.html';  // will be prepended with $cacheFileDir
$warn_details = 'meteoalarm-details.html';  // will be prepended with $cacheFileDir
$now = time(); // valid warnings this period
$future = $now + 36 * 3600; // at least start before
$image_url_prototype = './ajax-images/meteoalarm_##.svg';
#
# Overrides from Settings.php if available
global $SITE;
if (isset($SITE['tz'])) {$ourTZ = $SITE['tz']; }
if (isset($SITE['dateOnlyFormat'])) {$dateFormat = $SITE['dateOnlyFormat']; }
if (isset($SITE['timeOnlyFormat'])) {$timeFormatShort = $SITE['timeOnlyFormat']; }
if (isset($SITE['EUwarnings'])) {$alarm_area = $SITE['EUwarnings']; }
if (isset($SITE['EUminLevel'])) {$minAlertLevel = $SITE['EUminLevel']; }
if (isset($SITE['cacheFileDir']))     {$cacheFileDir = $SITE['cacheFileDir']; }
// end of overrides from Settings.php

#  --------------------------------- test values
#'HR803'; #
#$cache_max_age  = 900;
#$test_cap     =  './jsondata/capDE.json';
#$test_cap_dtl   = '';
#$now      = $now - 3*24*3600;
#$test     = 2*24*3600;
$test = 0;
#  --------------------------------- test values
#
# -------------------------------------- styling
#
$warncolors = array();
$warncolors[0] = '#fff';
$warncolors[1] = '#29d660'; # alert=none usually not posted on meteoalarm.org
$warncolors[2] = '#FFDB23'; # yellow from meteoalarm.org
$warncolors[3] = '#FF9500'; # orange from meteoalarm.org
$warncolors[4] = '#FF0100'; # red    from meteoalarm.org 
#
$warnlevels = array();
$warnlevels[0] = '--';
$warnlevels[1] = 'None'; # green
$warnlevels[2] = 'Moderate'; # yellow
$warnlevels[3] = 'Severe'; # orange
$warnlevels[4] = 'Extreme'; # red
$severities = array(
  'Minor' => 1,
  'Moderate' => 2,
  'Severe' => 3,
  'Extreme' => 4
);
$colors = array(
  'Green',
  'Yellow',
  'Orange',
  'Red',
  'Amber',
  'Moderate',
  'Severe',
  'Extreme'
);
#
$warntypes = array();
$warntypes[1] = 'wind';
$warntypes[2] = 'snow-ice'; #Low-temperature  Fog

#
$ownpagehtml = '';
#
$countries = array(
  'AT' => 'austria',
  'BA' => 'bosnia-herzegovina',
  'BE' => 'belgium',
  'BG' => 'bulgaria',
  'CH' => 'switzerland',
  'CY' => 'cyprus',
  'CZ' => 'czechia',
  'DE' => 'germany',
  'DK' => 'denmark',
  'EE' => 'estonia',
  'ES' => 'spain',
  'FI' => 'finland',
  'FR' => 'france',
  'GR' => 'greece',
  'HR' => 'croatia',
  'HU' => 'hungary',
  'IE' => 'ireland',
  'IL' => 'israel',
  'IS' => 'iceland',
  'IT' => 'italy',
  'LT' => 'lithuania',
  'LU' => 'luxembourg',
  'LV' => 'latvia',
  'MD' => 'moldova',
  'ME' => 'montenegro',
  'MK' => 'republic-of-north-macedonia',
  'MT' => 'malta',
  'NL' => 'netherlands',
  'NO' => 'norway',
  'PL' => 'poland',
  'PT' => 'portugal',
  'RO' => 'romania',
  'RS' => 'serbia',
  'SE' => 'sweden',
  'SI' => 'slovenia',
  'SK' => 'slovakia',
  'UK' => 'united-kingdom'
);
#
$lang_warn = array(
  'af' => 'Afrikaans',
  'bg' => '&#1073;&#1098;&#1083;&#1075;&#1072;&#1088;&#1089;&#1082;&#1080; &#1077;&#1079;&#1080;&#1082;',
  'ct' => 'Catal&agrave;',
  'dk' => 'Dansk',
  'nl' => 'Nederlands',
  'en' => 'English',
  'fi' => 'Suomi',
  'fr' => 'Fran&ccedil;ais',
  'de' => 'Deutsch',
  'el' => '&Epsilon;&lambda;&lambda;&eta;&nu;&iota;&kappa;&#940;',
  'ga' => 'Gaeilge',
  'hu' => 'Magyar',
  'it' => 'Italiano',
  'he' => '&#1506;&#1460;&#1489;&#1456;&#1512;&#1460;&#1497;&#1514;',
	'lt' => 'lietuvi&#371; kalba',
	'lv' => 'latvie&#353;u valoda',
  'no' => 'Norsk',
  'pl' => 'Polski',
  'pt' => 'Portugu&ecirc;s',
  'ro' => 'limba rom&#00226;n&#00259;',
  'es' => 'Espa&ntilde;ol',
  'se' => 'Svenska',
  'si' => 'Sloven&#353;&#269;ina',
	'sk' => 'Sloven&#269;ina',
	'sr' => 'Srpski',
  'de-DE' => 'Deutsch',
  'en-GB' => 'English',
  'es-ES' => 'Espa&ntilde;ol',
	'et-ET' => 'eesti keel',
  'fi-FI' => 'Suomi',
  'fr-FR' => 'Fran&ccedil;ais',
  'gr-GR' => '&Epsilon;&lambda;&lambda;&eta;&nu;&iota;&kappa;&#940;',
  'hr-HR' => 'Hrvatski',
  'it-IT' => 'Italiano',
  'ne-NL' => 'Nederlands',
  'po-PL' => 'Polski',
  'pt-PT' => 'Portugu&ecirc;s',
	'ru-RU' => '&rcy;&ucy;&scy;&scy;&kcy;&icy;&jcy; &yacy;&zcy;&ycy;&kcy;',
  'sv-SE' => 'Svenska'
); 
# Shim function if run outside of AJAX/PHP template set
# these must be before the missing function is called in the source
if(!function_exists('langtransstr')) {
	function langtransstr($item) {
		return($item);
	}
}
if(!function_exists('langtrans')) {
	function langtrans($item) {
		print $item;
		return;
	}
}

$disclaimer = langtransstr('This warning data is courtesy of and is Copyright &copy; by EUMETNET-METEOalarm') . 
  ' (http://www.meteoalarm.org/) '.
	langtransstr('and respective National Meteorological Services.').'<br/>'.PHP_EOL.
  langtransstr('Used with permission per'). 
	' www.meteoalarm.org <a href="https://meteoalarm.org/page/terms-and-conditions" target="_blank">' .
	langtransstr('Terms &amp; Conditions of Reuse'). '</a>.<br/>' . PHP_EOL .
  langtransstr('Time delays between this website and the www.meteoalarm.org website are possible.'). 
	'<br/>'. PHP_EOL .
  langtransstr('For the most up to date information about alert levels as published by the participating National Meteorological Services please use'). 
	' <a href="https://www.meteoalarm.org/">www.meteoalarm.org</a>.'.PHP_EOL;

$credits = '<small>'.$EUAversion.'<br/>adapted by <a href="https://saratoga-weather.org/wxtemplates/" target="_blank">Saratoga-Weather.org</a> with permission from wrnWarningEU-CAP.php script in <a href="https://pwsdashboard.com" target="_blank">pwsdashboard.com</a>.</small>';

$levelDescriptions = array(
'Yellow' => 'The weather is potentially dangerous. The weather phenomena that have been forecast are not unusual, but be attentive if you intend to practice activities exposed to meteorological risks. Keep informed about the expected meteorological conditions and do not take of any avoidable risk.',

'Orange' => 'The weather is dangerous. Unusual meteorological phenomena have been forecast. Damage and casualties are likely to happen. Be very vigilant and keep regularly informed about the detailed expected meteorological conditions. Be aware of the risks that might be unavoidable. Follow any advice given by your authorities.',

'Red' => 'The weather is very dangerous. Exceptionally intense meteorological phenomena have been forecast. Major damage and accidents are likely, in many cases with threat to life and limb, over a wide area. Keep frequently informed about detailed expected meteorological conditions and risks. Follow orders and any advice given by your authorities under all circumstances, be prepared for extraordinary measures.',
);

date_default_timezone_set($ourTZ);
$Status = '';

$warn_cache = $cacheFileDir.$warn_cache;
$warn_summary = $cacheFileDir.$warn_summary;
$warn_details = $cacheFileDir.$warn_details;

$aliases = array();
print '<!-- '.$EUAversion. ' -->'.PHP_EOL;

if(!is_numeric($minAlertLevel)) { $minAlertLevel = 2; }
if($minAlertLevel < 1 or $minAlertLevel > 4) { $minAlertLevel = 2; }
print "<!-- using minAlertLevel=$minAlertLevel for '".$colors[$minAlertLevel-1]. 
      "' or more severe alerts to display -->".PHP_EOL;

if(file_exists('meteoalarm-geocode-aliases.php')) {
	include_once('meteoalarm-geocode-aliases.php');
	print "<!-- meteoalarm-geocode-aliases.php included -->\n";
}

$codenames = array();
global $codenames;
if(file_exists('meteoalarm-codenames.json')) {
	$rawCodenames = file_get_contents('meteoalarm-codenames.json');
	if($rawCodenames !== false) {
		$codenames = json_decode($rawCodenames,true,JSON_INVALID_UTF8_IGNORE);
		if(json_last_error() == JSON_ERROR_NONE) {
	   print "<!-- meteoalarm-codenames.json loaded -->\n";
		} else {
			$err = decode_json_error(json_last_error());
			
		 print "<!-- problem decoding meteoalarm-codenames.json code=$err -->\n";
		}
	} else {
		print "<!-- unable to load meteoalarm-codenames.json -->\n";
	}
}


$warns = array();
$CountryWarnings = array();

if(!isset($alarm_area) or (isset($alarm_area) and strlen($alarm_area) < 5) ) {
	show_woops($EUAversion);
	return false;
}
$alarm_area = str_replace(' ','',$alarm_area);
$alarm_areas = explode(',', $alarm_area); #echo __LINE__.print_r($alarm_areas,true); exit;
$cntrs = array();
$text = ' areas=';
foreach ($alarm_areas as $area) {
  $cntr = substr($area, 0, 2);
  $text .= $area . ', ';
  $cntrs[$cntr] = $cntr;
} # echo __LINE__.print_r($alarm_areas,true).print_r($cntrs,true); exit;
$text = substr($text, 0, -2) . ' countries=';
foreach ($cntrs as $cntr) {
  $text .= $cntr . ', ';
}
#
#
if (file_exists("$warn_cache")) {
  $cache_age = $now - filemtime("$warn_cache");
}
else {
  $cache_age = $now;
}
#
if (isset($test_cap)) {
  $cache_age = 300;
}
#
#
if (array_key_exists('force', $_REQUEST) && trim($_REQUEST['force']) == '1') {
  $cache_age = $now;
}
#
if ($cache_age > $cache_max_age) {
  $warns = array();
  $items = 0;
  $used = - 1;
  $others = 0;
  $invalid = 0;
  $to_old = 0;
  $to_young = 0; #
  $multiple = 0;
  $updated = 0;
  $now = time();
  foreach ($cntrs as $country) #----- / country
  {
    $warn_url = 'https://hub.meteoalarm.org/api/v1/stream-buffers/feeds-' . $countries[$country] . '/warnings?include_geocodes=0&exclude_severity_minor=1'; # echo $warn_url; exit;
    $fl_to_load = $country . '_warnings'; #echo __LINE__.' "$warn_cache"='."$warn_cache".'  $warn_url='.$warn_url; exit;
    $load = warn_curl();
    if ($load === false) {
      $Status .= basename(__FILE__) . '(' . __LINE__ . ') invalid data load' . PHP_EOL;
			#print $Status;
      #return false;
			continue;
    }
    $array = json_decode($result, true); #echo $Status.__LINE__.print_r ($array,true); exit;
    $valid_data = true;
    if (!array_key_exists('warnings', $array) || count($array['warnings']) < 1) {
      $Status .= basename(__FILE__) . '(' . __LINE__ . ') no warnings in JSON file' . PHP_EOL;
      #echo $Status.__LINE__.print_r ($array,true); exit;
      continue;
    } // next country
    #
    $warnings = isset($array['warnings'])?$array['warnings']:array(); #  echo __LINE__.print_r($warnings,true); exit;
    foreach ($warnings as $warning) { #echo __LINE__.print_r($warning,true); exit;
      $id_alrt = $countries[$country] . '/' . $warning['uuid'];
      foreach ($warning as $alert) { #echo __LINE__.print_r($alert,true); exit;
        if (!is_array($alert) || !array_key_exists('info', $alert)) {
          continue;
        }
        if (!is_array($alert['info'])) {
          $alert['info'][] = $alert['info'];
        }
        $from_o = strtotime($alert['info'][0]['onset']);
        $from_e = isset($alert['info'][0]['effective'])?strtotime($alert['info'][0]['effective']):time();
        if (!array_key_exists('expires', $alert['info'][0])) {
          continue;
        }
        $from_l = strtotime($alert['info'][0]['expires']);
        if ($now > $from_l + $test) {
          continue;
        } // old expires already passed
        if ($from_e > ($now + 1 * 24 * 3600)) {
          continue;
        } // valid only after 48 hours
        #   $id_alrt= $alert['identifier'];
        $valid = false;
        $infos = array();
        foreach ($alert['info'] as $key => $info) { #echo  __LINE__.' '.$id_alrt.' id'.$key.PHP_EOL;
          #echo __LINE__.print_r ($info,true); exit;
          if (!array_key_exists('area', $info)) {
            continue;
          }
          $areas = $info['area'];
          if (!is_array($areas)) {
            $areas[0] = $areas;
          }
          $forus = array();
          foreach ($areas as $area) { #echo __LINE__.print_r ($area,true);
            if (!array_key_exists('geocode', $area)) {
              continue;
            }
            $geocodes = $area['geocode'];
            if (!is_array($geocodes)) {
              $geocodes[0] = $geocode;
            }
            foreach ($geocodes as $geocode) {
              if (!array_key_exists('value', $geocode)) {
                continue;
              }
              $value = $geocode['value'];
              if (!is_string($value)) {
                continue;
              }
							if(!array_key_exists('valueName',$geocode)) {
								continue;
							}
							$valueName = $geocode['valueName'];
							if (!is_string($valueName)) {
								continue;
							}
							
              if ($valueName == 'EMMA_ID' and in_array($value, $alarm_areas) ) {
                $forus[] = $value . '|' . $area['areaDesc'] . '||';
								continue;
              }
							if(isset($aliases["$value|$valueName"])) {
							  $nvalue = $aliases["$value|$valueName"]; 
								if(in_array($nvalue, $alarm_areas) ) {
									$t = langtransstr('Note: EMMA_ID [%s] alias of %s geocode [%s] was used for this alert.');
									$t = sprintf($t,$nvalue,$valueName,$value);
                  $forus[] = $nvalue.'*|'.$area['areaDesc'] .'|'.$t.'|';
								}
							}
						

            } // eo each geocode
            
          } // eo each area
          if (count($forus) == 0) {
            continue;
          } // not for one of our areas
          #  echo __LINE__.print_r($forus,true);
          #  echo __LINE__.print_r ($info,true);
          unset($info['area']);
          $lng = $info['language'];
          $info['forus'] = $forus;
					$info['sent'] = $alert['sent'];
					$info['sender'] = $alert['sender'];
          $warns[$id_alrt][$lng] = $info; #echo __LINE__.print_r($warning,true);  exit;
          
        } // eo alert info
        

        
      } // eo each info
      
    } // eo each alert
    
  } // eo each country
  $return = file_put_contents("$warn_cache", serialize($warns));
	file_put_contents($cacheFileDir.'warns-array.txt',var_export($warns,true)); # for debugging
} // eo all countries
else {
  $warns = unserialize(file_get_contents("$warn_cache"));
	$Status = "Loaded warning data from '$warn_cache'. Updated:". 
	   date($dateFormat.' '.$timeFormatShort,filemtime("$warn_cache")) . PHP_EOL;

}
#echo __LINE__.print_r($warns,true);  exit;
$max_color = 0;
$table = '';
$link = '';
$rows = 3;
$cln_vnt = '';
$nr = $wrnng = 0;
$languages = array();
$count = count($warns);
$AnchorCount = 0;

foreach ($warns as $warncode => $warn) {
  $cnt = count($warns); #echo  __LINE__.'count  ='.$cnt; exit;
  $cln_vnt = '';
  $languages = array();
  foreach ($warn as $lng => $arr) {
    if (!array_key_exists('description', $arr)) {
      $description = '';
    }
    else {
      $description = $arr['description'];
    }
    if (!array_key_exists('headline', $arr)) {
      $headline = '';
    }
    else {
      $headline = $arr['headline'];
    }
    if (!array_key_exists('instruction', $arr)) {
      $instruction = '';
    }
    else {
      $instruction = $arr['instruction'];
    }
    $languages[$lng] = array(
      'description' => $description,
      'headline' => $headline,
      'instruction' => $instruction
    );
  } #echo __LINE__.print_r($languages,true); exit;
  reset($warn); #echo __LINE__.' '.print_r($languages).print_r($warns); exit;
  foreach ($warn as $lng => $arr) {
    break;
  }
  $level = $type = ''; #echo __LINE__.' $lng='.$lng.' '.$arr['event'].PHP_EOL; exit;
  foreach ($arr['parameter'] as $check) {
    if ($check['valueName'] == 'awareness_level') {
      $level = $check['value'];
    }
    else {
      $type = $check['value'];
    }
  }
  list($nr_l, $color, $text) = explode(';', $level . ';;;');
  $nr_l = (int)trim($nr_l);
  $level = $nr_l;
	if($level < $minAlertLevel) {
		continue; # skip the alert
	}
  if ($level < 1 || $level > 4) {
    continue; # skip the alert
  }
  $severity = $warnlevels[$level];
  if ($level > $max_color) {
    $max_color = $level;
  }
  #
  list($nr_e, $event) = explode(';', $type . ';;');
  $nr_e = (int)trim($nr_e);
  $event = trim($event);
  $img_nr = $nr_l . $nr_e; #echo __LINE__.' $img_nr='.$img_nr; exit;
  $img_nr = $nr_e; #echo __LINE__.' $img_nr='.$img_nr; exit;
  $image_def = str_replace('##', $img_nr, $image_url_prototype);
  if (!file_exists($image_def) )
     {  $image_def = str_replace ('##','000',$image_url_prototype);}
  $arr['onset'] = strtotime($arr['onset']);
  $arr['expires'] = strtotime($arr['expires']);
  $ymd_frm = date($dateFormat . ' ', $arr['onset']);
  $ymd_to = date($dateFormat . ' ', $arr['expires']);
  if ($ymd_frm == $ymd_to) {
    $ymd_to = '';
  }
	$AnchorCount++;
	$alertAnchor = '<a name="alert'.$AnchorCount.'" id="alert'.$AnchorCount.'"></a>';
	
  $total_time = '<b>' . langtransstr('Valid') . ':&nbsp;</b>' . $ymd_frm . date($timeFormatShort, $arr['onset']) . '&nbsp;&nbsp;-&nbsp;&nbsp;' . $ymd_to . date($timeFormatShort, $arr['expires']);
  if ($cln_vnt == '') {
    $cln_vnt = ucfirst(trim(str_replace($colors, '', $arr['event'])));
  }
  foreach ($arr['forus'] as $region) {
    list($geocode, $areaDesc,$areaInfo) = explode('|', $region . '||');
    $table .= '<tr style="background-color: ' . $warncolors[$level] . '">' . PHP_EOL;
    $table .= '<td colspan="2">' . $alertAnchor . 
		      '<span style="margin-left: 5px; float: left;"><b>' . $cln_vnt . '&nbsp;&nbsp;&nbsp;' . $areaDesc . 
					'</b> <small>(' . $geocode . ')</small></span>' . '<span style="float: right; margin-right:5px;">' .
					 $total_time . '</span></td>' . PHP_EOL . '</tr>' . PHP_EOL;
    #$total_time = '&nbsp;';
		$alertAnchor = '';
  }
  $table .= '<tr style="background-color: ' . $warncolors[$level] . '">' . PHP_EOL;
  $table .= '<td style="vertical-align: top; width: 110px;">';
	$tevent = str_replace('-',' ',$event);
	$tevent = ucwords($tevent);
	$tevent = langtransstr($tevent);
  $table .= '<img src="' . $image_def . '" style="margin: 4px; width: 100px; max-width: 128px; " alt="' . $image_def . '" title="' . $tevent . '"/></td>' . PHP_EOL;
  $table .= '<td style="text-align: left;">';
  $table .= '
<span class="tab" style="">' . PHP_EOL;
  $other = $start = '';
  $display = 'block';
  $active = 'active';
  $margin = 'margin-left: 20px;';
  $wrnng++;
  $from = $to = '';
  if (array_key_exists('web', $arr)) {
    $from = trim($arr['web']);
    $to = '<a href="' . $from . '" target="_blank">' . $from . '</a>';
  } #echo __LINE__.print_r($arr,true).PHP_EOL.$from.PHP_EOL.$to; exit;
  
	if(!isset($CountryWarnings[substr($geocode,0,2)][$areaDesc][$warncolors[$level]."|".$image_def.'|'.$event]) ) {
		
	  $CountryWarnings[substr($geocode,0,2)][$areaDesc][$warncolors[$level]."|".$image_def.'|'.$event] = "alert".$AnchorCount;
	} else {
		$CountryWarnings[substr($geocode,0,2)][$areaDesc][$warncolors[$level]."|".$image_def.'|'.$event] .= ",alert".$AnchorCount;
	}
	
  foreach ($languages as $language => $text) {
    $nr++;
    $lngtxt = $language; # echo __LINE__.print_r($text,true); exit;
    $lngshrt = substr($language, 0, 2);
    if (array_key_exists($language, $lang_warn)) {
      $lngtxt = $lang_warn[$language];
    }
    elseif (array_key_exists($lngshrt, $lang_warn)) {
      $lngtxt = $lang_warn[$lngshrt];
    }
    $start .= '<label class="t' . $wrnng . 'tablinks tablinks ' . $active . '"  style="' . $margin . '" onclick="openTab(event,\'t' . $wrnng . '\', \'t' . $wrnng . '-' . $nr . '\')" id="' . $language . $wrnng  . '">&nbsp;' . $lngtxt . '&nbsp;</label> ' . PHP_EOL;
    $margin = '';
    $other .= '<span id="t' . $wrnng . '-' . $nr . '" class="t' . $wrnng . 'tabcontent tabcontent" style="clear: left; display: ' . $display . ';">' . PHP_EOL;
    $display = 'none';
    $active = '';
    $hdln1 = trim($text['headline']);
    $hdln2 = trim($text['description']);
    $hdln2 = str_replace($from, $to, $hdln2);
    if ($hdln2 == $hdln1) {
      $hdln2 = '';
    }
    if ($hdln1 <> '' && $hdln2 <> '') {
      $hdln1 .= '<br />';
    }
    if ($hdln1 . $hdln2 <> '') {
      $other .= '<b style="text-align: center; width: 100%; display: block;">' . $hdln1 . '</b>' . PHP_EOL . '<br />' . PHP_EOL;
    }

    $txtbr = str_replace(PHP_EOL, '<br />', $hdln2);
    $other .= $txtbr . '<br />' . PHP_EOL;
    $instruction = $text['instruction'];
    $instruction = str_replace($from, $to, $instruction);
    if ($instruction <> '') {
      $other .= '<br />' . $instruction . '<br />' . PHP_EOL;
    }
    $other .= '</span>' . PHP_EOL;

  } // eo e texts
#-------------------------------------------
    $nr++;
    $display = 'none';
    $active = '';
		$language = 'info';
		$lngtxt = '<img src="'.str_replace('##','info',$image_url_prototype).'" alt="info" title="info"/>';
    $start .= '<label class="t' . $wrnng . 'tablinks tablinks ' . $active . '"  style="' . $margin . '" onclick="openTab(event,\'t' . $wrnng . '\', \'t' . $wrnng . '-' . $nr . '\')" id="'  . $language . $wrnng . '">&nbsp;' . $lngtxt . '&nbsp;</label> ' . PHP_EOL;
    $margin = '';
    $other .= '<span id="t' . $wrnng . '-' . $nr . '" class="t' . $wrnng . 'tabcontent tabcontent" style="clear: left; display: ' . $display . ';">' . PHP_EOL;
    $hdln1 = langtransstr('Information');
		if(isset($arr['web'])) {
		  $P = parse_url($arr['web']);
		  $tUrl = (isset($P['scheme']) and isset($P['host']))?$P['scheme'].'://'.$P['host']:'';
		  $tUrl .= (isset($P['port']))?':'.$P['port']:'';
		  $tUrl .= '/';
      $hdln2 = langtransstr('Originator'). ': <a href="'.$tUrl.'" target="_blank">'.$arr['senderName'].'</a><br/>'.PHP_EOL;
		} else {
			$hdln2 = langtransstr('Originator'). ': n/a<br/>'.PHP_EOL;
		}
		#$hdln2 .= langtransstr('Sent by').': '.$arr['sender'].'<br/>'.PHP_EOL;
		$hdln2 .= langtransstr('Issued').':   <b>'.
		   date($dateFormat.' '.$timeFormatShort,strtotime($arr['sent'])).'</b><br/>'.PHP_EOL;
    foreach ($arr['forus'] as $region) {
      list($geocode, $areaDesc,$areaInfo) = explode('|', $region . '||');

			if($areaInfo !== '') {
				$hdln2 .= $areaInfo.'<br/>'.PHP_EOL;
			}
		}
    if ($hdln1 <> '' && $hdln2 <> '') {
      $hdln1 .= '<br />'.PHP_EOL;
    }
		
    $other .= '<b style="text-align: center; width: 100%; display: block;">' . $hdln1 . '</b>' . PHP_EOL . 
		    '<br />' . PHP_EOL . $hdln2;
		
    $instruction = '';
    $instruction = str_replace($from, $to, $instruction);
    if ($instruction <> '') {
      $other .= '<br />' . $instruction . '<br />' . PHP_EOL;
    }
    $other .= '</span>' . PHP_EOL;
//*/
#-------------------------------------------
  #
  #  if (count ($languages) < 2)
  #    {  $start = '';}
  $table .= $start . $other;
  $table .= '</span></td>' . PHP_EOL;
  $table .= '</tr>' . PHP_EOL;
#  $table .= '<tr style="background-color: transparent; height: 10px;"><td colspan="2" style="font-size: 8px;" title="' . $warncode . '">' . '<a href="https://hub.meteoalarm.org/warnings/feeds-' . $warncode . '" target="_blank">link</a>' . '</td></tr>' . PHP_EOL;
  $table .= '<tr><td>&nbsp;</td></tr>'.PHP_EOL;

} // eo for each warning;
$table = '<table style="width: 100%; border-collapse: collapse; margin-top: 8px; " >
' . $table . '
</table>';

ksort($CountryWarnings);

print "<!-- CountryWarnings:\n".var_export($CountryWarnings,true)." -->\n";

if ($count > 1) {
  $text = langtransstr('Multiple warnings');
}
else {
  $text = $cln_vnt;
}
/*
$CountryWarnings = 
array (
  'NO' => 
  array (
    'Østlandet og østlige deler av Agder' => 
    array (
      '#FF9500|./ajax-images/meteoalarm_8.svg|forest-fire' => 'alert1',
    ),
  ),
  'RS' => 
  array (
    'Istocna Srbija' => 
    array (
      '#FF9500|./ajax-images/meteoalarm_3.svg|thunderstorm' => 'alert2,alert4,alert7,alert13',
      '#FF9500|./ajax-images/meteoalarm_10.svg|rain' => 'alert3,alert6,alert9,alert15',
      '#FFDB23|./ajax-images/meteoalarm_5.svg|high-temperature' => 'alert5,alert8,alert14',
      '#FFDB23|./ajax-images/meteoalarm_3.svg|thunderstorm' => 'alert10,alert11,alert17',
      '#FFDB23|./ajax-images/meteoalarm_10.svg|rain' => 'alert12',
      '#FFDB23|./ajax-images/meteoalarm_1.svg|wind' => 'alert16',
    ),
  ),
);

*/
/*
$wrnStrings = '<div style="text-align: center; position: absolute;top: 18px;  width: 100%; height: 60px;  font-size: 12px; background-color: ' . $warncolors[$max_color] . ';">
<div style="color: black;   margin-top: 4px;"><b>MeteoAlarm</b><br />' . $text . '<br />';
$wrnHref = '<a href="' . $detail_page_url . '">';
$wrnStrings .= $wrnHref . '<img src="'.str_replace('##','info',$image_url_prototype).'" alt="info" title="info"/>' .'
</a>
</div>
</div>';
*/
if (count($CountryWarnings) < 1) {
	$ownpagehtml = "<!-- get-meteoalarm-warning-inc: begin $warn_details -->".PHP_EOL;
	$ownpagehtml .= '<div align="center" class="advisoryBox" style="background-color: #29d660; width: 625px;margin: 0 auto !important;">'.PHP_EOL;
	$ownpagehtml .= '<!-- Fetch Status:'.PHP_EOL.$Status.PHP_EOL.' -->'.PHP_EOL;
	$ownpagehtml .= langtransstr('No current alerts for').': "'.format_emmaids($alarm_area).'".'.PHP_EOL;
	$ownpagehtml .= "</div><!--  get-meteoalarm-warning-inc: end  $warn_details -->".PHP_EOL;
	if (file_put_contents($warn_details,$ownpagehtml.PHP_EOL)) {
		print "<!-- get-meteoalarm-warning-inc: saved details to $warn_details file -->".PHP_EOL;
	} else {
		print "<!-- get-meteoalarm-warning-inc: unable to save details to $warn_details -->".PHP_EOL;
	}
	$ownpagehtml = "<!-- get-meteoalarm-warning-inc: begin $warn_summary -->".PHP_EOL;
	$ownpagehtml .= '<div align="center" class="advisoryBox" style="background-color: #29d660; width: 625px;margin: 0 auto !important;">'.PHP_EOL;
	$ownpagehtml .= '<!-- Fetch Status:'.PHP_EOL.$Status.PHP_EOL.' -->'.PHP_EOL;
	$ownpagehtml .= langtransstr('No current alerts for').': "'.format_emmaids($alarm_area).'".'.PHP_EOL;
	$ownpagehtml .= "</div><!--  get-meteoalarm-warning-inc: end  $warn_summary -->".PHP_EOL;
	if (file_put_contents($warn_summary,$ownpagehtml.PHP_EOL)) {
		print "<!-- get-meteoalarm-warning-inc: saved summary to $warn_summary file -->".PHP_EOL;
	} else {
		print "<!-- get-meteoalarm-warning-inc: unable to save summary to $warn_summary -->".PHP_EOL;
	}
	
  return false;
}

# new Summary warnings to be saved with links to $warn_summary
$wrnStrings =  "<!-- get-meteoalarm-warning-inc: begin summary $warn_summary -->".PHP_EOL;
$wrnStrings .= '<div align="center" class="advisoryBox" style="text-align: left;background-color: lightyellow; width: 625px;margin: 0 auto !important;">'.PHP_EOL;
$wrnStrings .= '<p style="text-align: center;width: 99%;"><strong>'.langtransstr('Watches/Warnings/Advisories').'</strong></p>'.PHP_EOL;
$wrnStrings .= '<table>'.PHP_EOL;

$alertdivstyle = 'width: 40px !important; height:40px !important; border: 1px solid black; margin: 2px; display: block; float: left;';
$alerticonstyle = ' margin: 4px; width: 32px; height: 32px;';

foreach ($CountryWarnings as $country => $wlist) {
	$country_name = $countries[$country];
	$country_name = str_replace('-',' ',$country_name);
	$country_name = ucwords($country_name);
#	$wrnStrings .= "<tr><td colspan=\"2\" style=\"text-align:left;\"><strong>".langtransstr($country_name).":</strong></td></tr>".PHP_EOL;
	
	foreach ($wlist as $areaname => $alertlist) {
		$wrnStrings .= "<tr><td>";
		
		foreach ($alertlist as $vals => $anchor) {
			 # debugging
			 list($color,$image_def,$event) = explode('|',$vals);
			 $event = str_replace('-',' ',$event);
			 $event = ucwords($event);
			 $event = langtransstr($event);
			 $anchors = explode(',',$anchor.',');
			 $useanchor = $anchors[0];
			 $wrnStrings .= "<div style=\"background-color: ".$color." !important;".$alertdivstyle."\">";
			 $wrnStrings .= '<a href="'.$detail_page_url.'#'.$useanchor.'">';
			 $wrnStrings .= '<img src="'.$image_def.'" style="'.$alerticonstyle.'" alt="'.$event.'" title="'.$event.'"/></a></div>&nbsp;'.PHP_EOL;
			 
			 #print "<!-- '$country_name' - '$areaname' '$vals' '$anchor' -->\n";
		} # end each icon/alert
		$wrnStrings .= "</td><td style=\"text-align:left;\">".$areaname.', '.langtransstr($country_name)."</td></tr>".PHP_EOL;
	} # end area name
}
$wrnStrings .= '</table>'.PHP_EOL;
$wrnStrings .= "</div>\n".PHP_EOL;
$wrnStrings .= "<!-- get-meteoalarm-warning-inc: end summary $warn_summary -->".PHP_EOL;

if (file_put_contents($warn_summary,$wrnStrings.PHP_EOL)) {
	print "<!-- get-meteoalarm-warning-inc: saved summary to $warn_summary file -->".PHP_EOL;
} else {
	print "<!-- get-meteoalarm-warning-inc: unable to save summary to $warn_summary -->".PHP_EOL;
}
#
$ownpagehtml = "<!-- get-meteoalarm-warning-inc: begin $warn_details -->".PHP_EOL;
$ownpagehtml .= '<!-- Fetch Status:'.PHP_EOL.$Status.PHP_EOL.' -->'.PHP_EOL;

$ownpagehtml .= '<script type="text/javascript">
// <![CDATA[

if(typeof document.createStyleSheet === "undefined") {
    document.createStyleSheet = (function() {
     function createStyleSheet(href) {
      if(typeof href !== "undefined") {
       var element = document.createElement("link");
       element.type = "text/css";
       element.rel = "stylesheet";
       element.href = href;}
      else {
       var element = document.createElement("style");
       element.type = "text/css";}
      document.getElementsByTagName("head")[0].appendChild(element);
      var sheet = document.styleSheets[document.styleSheets.length - 1];
      if(typeof sheet.addRule === "undefined")
      { sheet.addRule = addRule;}
      if(typeof sheet.removeRule === "undefined")
      { sheet.removeRule = sheet.deleteRule;}
      return sheet;
     }
     function addRule(selectorText, cssText, index) {
      if(typeof index === "undefined") { index = this.cssRules.length;}
      this.insertRule(selectorText + " {" + cssText + "}", index);
     }
     return createStyleSheet;
    })();
}
var sheet = document.createStyleSheet();
sheet.addRule(".tab","overflow: hidden; display: block; border: 0px solid #ccc; background-color: white; text-align: left; margin:  4px; margin-left: 0px;");
sheet.addRule(".tab span","text-align: left; margin:  4px;");
sheet.addRule(".tab label","float: left; border-radius: 4px; background-color: #ccc; border: 1px solid #ddd; cursor: pointer; margin: 3px; margin-bottom: 0px; padding: 3px; ");
sheet.addRule(".tab label:hover","background-color: white;");
sheet.addRule(".tab label.active","border-bottom-right-radius: 0px; border-bottom-left-radius: 0px; background-color: transparent; border: 1px solid black; border-bottom: 1px solid white;");
sheet.addRule(".tabcontent","display: none; border-top: none;");
sheet.addRule(".tab a"," text-decoration: underline; color:blue !important;");
function openTab(evt, block,  spanName) {
  var i, tabcontent, tablinks;
  var clssnm    = block+"tabcontent";
  tabcontent = document.getElementsByClassName(clssnm);
  
  for (i = 0; i < tabcontent.length; i++) 
   {tabcontent[i].style.display = "none";}
   
  
  tablinks = document.getElementsByClassName(block+"tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(spanName).style.display = "block";
  evt.currentTarget.className += " active";
}
// ]]>
</script>
' . $table;

$ownpagehtml .= PHP_EOL.
   '<p style="width: 100%; margin: 8px auto; padding-bottom: 5px; background-color: #DCDCDC;text-align: center; border: 1px solid black;">'.
      $disclaimer.'<br />'.PHP_EOL.$credits.'</p>'.PHP_EOL;
$ownpagehtml .= "<!--  get-meteoalarm-warning-inc: end  $warn_details -->".PHP_EOL;

if (file_put_contents($warn_details,$ownpagehtml.PHP_EOL)) {
	print "<!-- get-meteoalarm-warning-inc: saved details to $warn_details file -->".PHP_EOL;
} else {
	print "<!-- get-meteoalarm-warning-inc: unable to save details to $warn_details -->".PHP_EOL;
}

return true;

function format_emmaids($alert_area) {
	global $codenames;
	
	$a = str_replace(' ','',$alert_area);
	$areas = explode(',',$a.',');
 	$out = array();
	foreach ($areas as $i => $code) {
		if(empty($code)) { continue; }
		if(isset($codenames[$code])) {
			$out[] = $codenames[$code].'('.$code.')';
		} else {
			$out[] = langtrans('Name not available for').'('.$code.')';
		}
	}
	$output = join(', ',$out);
	return $output;
}
#------------------------------------------------------------------
function warn_curl() {
  global $warn_url, $Status, $result, $test_cap, $fl_to_load;
  $result = '';
  if (isset($test_cap) && strlen($test_cap) > 2) {
    $Status .= basename(__FILE__) . ' (' . __LINE__ . ') test_file ' . $test_cap . ' is used' . PHP_EOL;
    $result = file_get_contents($test_cap);
  }
  else {
    $start_time = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $warn_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // data timeout
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20120424 Firefox/12.0');
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);
    $end = microtime(true);
    $passed = $end - $start_time;
    if ($passed < 0.0001) {
      $string1 = '< 0.0001';
    }
    else {
      $string1 = round($passed, 4);
    }
    $CHECK_HTTP_CODES = array(
      '404',
      '429',
      '502',
      '500'
    );
    if (in_array($info['http_code'], $CHECK_HTTP_CODES)) {
      $Status .= basename(__FILE__) . ' (' . __LINE__ . ') ' . $fl_to_load . ': time spent: ' . $string1 . ' - PROBLEM => http_code: ' . $info['http_code'] . ', no valid data ' . $warn_url . PHP_EOL;
      $Status .= basename(__FILE__) . ' (' . __LINE__ . ') url used ' . $warn_url . PHP_EOL;
      return false;
    }
    if ($error <> '') {
      $Status .= basename(__FILE__) . ' (' . __LINE__ . ') ' . $fl_to_load . ': time spent: ' . $string1 . ' -  invalid CURL ' . $error . ' ' . $warn_url . PHP_EOL;
      return false;
    }
    else {
      $Status .= basename(__FILE__) . ' (' . __LINE__ . ') ' . $fl_to_load . ': time spent: ' . $string1 . ' -  CURL OK for ' . $warn_url . PHP_EOL;
    }
    $result = trim($result);
  }
  #echo $Status.$result;		exit;
  return true;
} // eof warn_curl

function show_woops($version) {
	global $warn_details,$warn_summary;
	
	$ownpagehtml = '<p style="width: 100%; margin: 8px auto; padding-bottom: 5px; background-color: #DCDCDC;text-align: center; border: 1px solid black;">'.$version.'<br/><br/><b>Configuration needed:</b><br/>'.PHP_EOL;
	$ownpagehtml .= 'The EMMA_ID(s) are not specified in <i>Settings.php</i> <b>$SITE[\'EUwarnings\']</b> entry.<br/>'.PHP_EOL;
	$ownpagehtml .= 'Use <a href="https://saratoga-weather.org/meteoalarm-map/" target="_blank">the meteoalarm map</a>';
	$ownpagehtml .= ' to locate the EMMA_ID for your area, and put it in the <i>Settings.php</i> <b>$SITE[\'EUwarnings\']</b> entry.<br/>'.PHP_EOL;
	$ownpagehtml .= 'You may use more than one EMMA_ID if you like.  Just separate them with a comma (,) like<br/><br/>'.PHP_EOL;
	$ownpagehtml .= '<b>$SITE[\'EUwarnings\'] = \'DK002,DK004,DK005\';</b><br/><br/>'.PHP_EOL;
	$ownpagehtml .= 'You may use more than one country\'s EMMA_ID, but be aware that each country specified will increase the delay in page loading due to data access.<br/><br/>'.PHP_EOL;
	$ownpagehtml .= 'You must also include in <i>Settings.php</i>:<br/><br/>'.PHP_EOL.
                  '  <b>$SITE[\'useMeteoalarm\'] = true;</b><br/><br/>'.PHP_EOL.
                  'to activate the displays in both wxindex.php and wxadvisory.php.</p>'.PHP_EOL;

	
	if (file_put_contents($warn_details,$ownpagehtml.PHP_EOL)) {
		print "<!-- get-meteoalarm-warning-inc: saved details to $warn_details file -->".PHP_EOL;
	} else {
		print "<!-- get-meteoalarm-warning-inc: unable to save details to $warn_details -->".PHP_EOL;
	}
	if (file_put_contents($warn_summary,$ownpagehtml.PHP_EOL)) {
		print "<!-- get-meteoalarm-warning-inc: saved summary to $warn_summary file -->".PHP_EOL;
	} else {
		print "<!-- get-meteoalarm-warning-inc: unable to save summary to $warn_summary -->".PHP_EOL;
	}

	return;
	
}
function decode_json_error($err) {

	$out = "$err";
	
	switch ($err) {
		case JSON_ERROR_NONE:
				$out .= ' - No errors';
		break;
		case JSON_ERROR_DEPTH:
				$out .= ' - Maximum stack depth exceeded';
		break;
		case JSON_ERROR_STATE_MISMATCH:
				$out .= ' - Underflow or the modes mismatch';
		break;
		case JSON_ERROR_CTRL_CHAR:
				$out .= ' - Unexpected control character found';
		break;
		case JSON_ERROR_SYNTAX:
				$out .= ' - Syntax error, malformed JSON';
		break;
		case JSON_ERROR_UTF8:
				$out .= ' - Malformed UTF-8 characters, possibly incorrectly encoded';
		break;
		default:
				$out .= ' - Unknown error';
		break;
	}

	$out .= PHP_EOL;
	
	return($out);

	
}
