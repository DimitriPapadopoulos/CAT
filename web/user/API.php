<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * AJAX backend for the user GUI
 *
 * @package UserAPI
 */
include(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");
$API = new \core\UserNetAPI();
$validator = new web\lib\common\InputValidation();
$loggerInstance = new \core\common\Logging();

const LISTOFACTIONS = [
    'listLanguages',
    'listCountries',
    'listIdentityProviders',
    'listAllIdentityProviders',
    'listIdentityProvidersWithProfiles',
    'listProfiles', // needs $idp set - abort if not
    'listDevices',
    'generateInstaller', // needs $device and $profile set
    'downloadInstaller', // needs $device and $profile set optional $generatedfor
    'profileAttributes', // needs $profile set
    'sendLogo', // needs $idp and $disco set
    'sendFedLogo', // needs $federation
    'deviceInfo', // needs $device and profile set
    'locateUser',
    'detectOS',
    'orderIdentityProviders',
    'getUserCerts',
];

function getRequest($varName, $filter) {
    $safeText = ["options"=>["regexp"=>"/^[\w\d-]+$/"]];
    switch ($filter) {
        case 'safe_text':
            $out = filter_input(INPUT_GET, $varName, FILTER_VALIDATE_REGEXP, $safeText) ?? filter_input(INPUT_POST, $varName, FILTER_VALIDATE_REGEXP, $safeText);
            break;
        case 'int':
            $out = filter_input(INPUT_GET, $varName, FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, $varName, FILTER_VALIDATE_INT);
            break;
        default:
            $out = NULL;
            break;
    }
    return $out;
}

// make sure this is a known action
$action = getRequest('action', 'safe_text');
if (!in_array($action, LISTOFACTIONS)) {
    throw new Exception("Unknown action used.");
}

$langR = getRequest('lang', 'safe_text');
$lang = $langR ? $validator->supportedLanguage($langR) : FALSE;
$deviceR = getRequest('device', 'safe_text');
$device = $deviceR ? $validator->Device($deviceR) : FALSE;
$idpR = getRequest('idp', 'int');
$idp = $idpR ? $validator->IdP($idpR)->identifier : FALSE;
$profileR = getRequest('profile', 'int');
$profile = $profileR ? $validator->Profile($profileR)->identifier : FALSE;
$federationR = getRequest('federation', 'safe_text');
$federation = $federationR ? $validator->Federation($federationR)->tld : FALSE;
$disco = getRequest('disco', 'int');
$width = getRequest('width', 'int') ?? 0;
$height = getRequest('height', 'int') ?? 0;
$sort = getRequest('sort', 'int') ?? 0;
$generatedfor = getRequest('generatedfor', 'safe_text') ?? 'user';
$token = getRequest('token', 'safe_text');
$idR = getRequest('id', 'safe_text');
$id = $idR ? $idR : FALSE;

switch ($action) {
    case 'listLanguages':
        $API->JSON_listLanguages();
        break;
    case 'listCountries':
        $API->JSON_listCountries();
        break;
    case 'listIdentityProviders':
        if ($federation === FALSE) {
           $federation = $id ? $validator->Federation($id)->tld : FALSE;
        }
        if ($federation === FALSE) { // federation is a mandatory parameter!
            exit;
        }
        $API->JSON_listIdentityProviders($federation);
        break;
    case 'listAllIdentityProviders':
        $API->JSON_listIdentityProvidersForDisco();
        break;
        case 'listIdentityProvidersWithProfiles':
        $API->JSON_ListIdentityProvidersWithProfiles();
        break;
    case 'listProfiles': // needs $idp set - abort if not
        if ($idp === FALSE) {
           $idp = $id ? $validator->IdP($id)->identifier : FALSE;
        }
        if ($idp === FALSE) {
            exit;
        }
        $API->JSON_listProfiles($idp, $sort);
        break;
    case 'listDevices':
        if ($profile === FALSE) {
           $profile = $id ? $validator->Profile($id)->identifier : FALSE;
        }
        if ($profile === FALSE) {
            exit;
        }
        $API->JSON_listDevices($profile);
        break;
    case 'generateInstaller': // needs $device and $profile set
        if ($device === FALSE) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->JSON_generateInstaller($device, $profile);
        break;
    case 'downloadInstaller': // needs $device and $profile set optional $generatedfor
        if ($device === FALSE) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->downloadInstaller($device, $profile, $generatedfor);
        break;
    case 'profileAttributes': // needs $profile set
        if ($profile === FALSE) {
           $profile = $id ? $validator->Profile($id)->identifier : FALSE;
        }
        if ($profile === FALSE) {
            exit;
        }
        $API->JSON_profileAttributes($profile);
        break;
    case 'sendLogo': // needs $idp and $disco set
        if ($idp === FALSE) {
           $idp = $id ? $validator->IdP($id)->identifier : FALSE;
        }
        if ($idp === FALSE) {
            exit;
        }
        if ($disco == 1) {
            $width = 120;
            $height = 40;
        }
        $API->sendLogo($idp, "idp", $width, $height);
        break;
    case 'sendFedLogo': // needs $federation
        if ($federation === FALSE) {
            if ($idp === FALSE) {
            exit;
        }
            $API->sendLogo($idp, "federation_from_idp", $width, $height);
        } else {
            $API->sendLogo($federation, "federation", $width, $height);
        }
        break;        
    case 'deviceInfo': // needsdevice and profile set
        if ($device === FALSE) {
            $device = $id;
        }
        if ($device === FALSE || $profile === FALSE) {
            exit;
        }
        $API->deviceInfo($device, $profile);
        break;
    case 'locateUser':
        $API->JSON_locateUser();
        break;
    case 'detectOS':
        $API->JSON_detectOS();
        break;
    case 'orderIdentityProviders':
        $coordinateArray = NULL;
        if ($location) {
            $coordinateArrayRaw = explode(':', $location);
            $coordinateArray = ['lat' => $coordinateArrayRaw[0], 'lon' => $coordinateArrayRaw[1]];
        }
        if ($federation === FALSE) { // is this parameter mandatory? The entire API call is not mentioned in UserAPI.md documentation currently
            $federation = "";
        }
        $API->JSON_orderIdentityProviders($federation, $coordinateArray);
        break;
    case 'getUserCerts':
        $API->JSON_getUserCerts($token);
        break;
}

// $loggerInstance->debug(4, "UserAPI action: " . $action . ':' . $lang !== FALSE ? $lang : '' . ':' . $profile . ':' . $device . "\n");
