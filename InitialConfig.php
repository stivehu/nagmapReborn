<?php

if (!defined('NGR_DOCUMENT_ROOT')) {
    define('NGR_DOCUMENT_ROOT', dirname(__FILE__) == '/' ? '' : dirname(__FILE__));
}

include_once(NGR_DOCUMENT_ROOT . '/src/NagmapReborn/ConfigLoader.php');
include_once(NGR_DOCUMENT_ROOT . '/src/NagmapReborn/Helper.php');

// Check if the config file exist.
if (!file_exists("config.php")) {
    return jsonResponse(['error' => ["The 'config.php' file was not found in the project folder. Please check the existence of the file and if the name is correct and try again."]], 400);
}

// Check if the translation file informed exist.
if (!file_exists(NGR_DOCUMENT_ROOT . "/langs/" . config('ngreborn.language') . ".json")) {
    include_once('config.php');
    if (isset($nagios_cfg_file))
        return jsonResponse(['error' => ["It looks like you just updated Nagmap Reborn, <b><a href='https://github.com/jocafamaka/nagmapReborn/wiki' target='_blank'>see here</a></b> the changes that are necessary for version migration."]], 400);
    else
        return jsonResponse(['error' => [sprintf("%s.json does not exist in the languages folder! Please set the proper LANG option in Nagmap Reborn config file!", config('ngreborn.language'))]], 400);
}

// Load language
require_once(NGR_DOCUMENT_ROOT . "/src/NagmapReborn/i18n.class.php");
$i18n = new i18n(NGR_DOCUMENT_ROOT . "/langs/" . config('ngreborn.language') . ".json", NGR_DOCUMENT_ROOT . "/cache/");
$i18n->init();

$fails = [];

// GENERAL
if (!is_string(config('general.cfg_file')))
    $fails[] = L::config_error('general.cfg_file', config('general.cfg_file'));

if (!file_exists(config('general.cfg_file')))
    $fails[] = L::file_not_find_error('general.cfg_file');

if (!is_string(config('general.status_file')))
    $fails[] = L::config_error('general.status_file', config('general.status_file'));

if (!file_exists(config('general.status_file')))
    $fails[] = L::file_not_find_error('general.status_file');

if (!is_int(config('general.debug')))
    $fails[] = L::config_error("DEBUG", config('general.debug'));


// MAP
$centre = explode(",", config('map.centre'));
if (!is_string(config('map.centre')) || count($centre) != 2)
    $fails[] = L::config_error("map.centre", config('map.centre'));

if (!(is_float(config('map.zoom')) || is_int(config('map.zoom'))))
    $fails[] = L::config_error("map.zoom", config('map.zoom'));

if (!is_string(config('map.style')))
    $fails[] = L::config_error('map.style', config('map.style'));


// NGREBORN
if (!is_string(config('ngreborn.filter_hostgroup')))
    $fails[] = L::config_error("filter_hostgroup", config('ngreborn.filter_hostgroup'));

if (!is_string(config('ngreborn.filter_service')))
    $fails[] = L::config_error("ngreborn.filter_service", config('ngreborn.filter_service'));

if ((!is_int(config('ngreborn.changes_bar_mode'))) || (config('ngreborn.changes_bar_mode') < 0) || (config('ngreborn.changes_bar_mode') > 3))
    $fails[] = L::config_error("ngreborn.changes_bar_mode", config('ngreborn.changes_bar_mode'));

if (!(is_float(config('ngreborn.changes_bar_size')) || is_int(config('ngreborn.changes_bar_size'))))
    $fails[] = L::config_error("ngreborn.changes_bar_size", config('ngreborn.changes_bar_size'));

if (!(is_float(config('ngreborn.font_size')) || is_int(config('ngreborn.font_size'))))
    $fails[] = L::config_error("ngreborn.font_size", config('ngreborn.font_size'));

if (!is_int(config('ngreborn.bar_filter')))
    $fails[] = L::config_error("ngreborn.bar_filter", config('ngreborn.bar_filter'));

if (!is_int(config('ngreborn.priorities.unknown')))
    $fails[] = L::config_error("ngreborn.priorities.unknown", config('ngreborn.priorities.unknown'));

if (!is_int(config('ngreborn.priorities.up')))
    $fails[] = L::config_error("ngreborn.priorities.up", config('ngreborn.priorities.up'));

if (!is_int(config('ngreborn.priorities.warning')))
    $fails[] = L::config_error("ngreborn.priorities.warning", config('ngreborn.priorities.warning'));

if (!is_int(config('ngreborn.priorities.critical')))
    $fails[] = L::config_error("ngreborn.priorities.critical", config('ngreborn.priorities.critical'));

if (!is_int(config('ngreborn.priorities.down')))
    $fails[] = L::config_error("ngreborn.priorities.down", config('ngreborn.priorities.down'));

if (!is_int(config('ngreborn.play_sound')))
    $fails[] = L::config_error("ngreborn.play_sound", config('ngreborn.play_sound'));

if (!is_int(config('ngreborn.icon_style')) || config('ngreborn.icon_style') > 2 || config('ngreborn.icon_style') < 0)
    $fails[] = L::config_error("ngreborn.icon_style", config('ngreborn.icon_style'));

if (!is_int(config('ngreborn.lines')))
    $fails[] = L::config_error("ngreborn.lines", config('ngreborn.lines'));

if (!(is_float(config('ngreborn.time_update')) || is_int(config('ngreborn.time_update'))))
    $fails[] = L::config_error("ngreborn.time_update", config('ngreborn.time_update'));

if (!is_int(config('ngreborn.reporting')) || (config('ngreborn.reporting') < 0) || (config('ngreborn.reporting') > 1))
    $fails[] = L::config_error("ngreborn.reporting", config('ngreborn.reporting'));


// SECURITY
if (!is_string(config('security.key')))
    $fails[] = L::config_error("security.key", config('security.key'));

if ((config('security.use_auth') < 0) || (config('security.use_auth') > 1)) {
    $fails[] = L::config_error("security.use_auth", config('security.use_auth'));
} else {
    if (config('security.use_auth') == 1) {
        if (empty(config('security.user')) || empty(config('security.user_pass')))
            $fails[] = L::emptyUserPass;
    }
}


// MODULES
if (!extension_loaded('mbstring'))
    $fails[] = L::moduleError('mbstring');

if (!extension_loaded('json'))
    $fails[] = L::moduleError('json');


if (!empty($fails)) {
    return jsonResponse(['error' => $fails], 400);
}

require_once(NGR_DOCUMENT_ROOT . '/src/NagmapReborn/marker.php');

if (!empty($fails)) {
    return jsonResponse(['error' => $fails], 400);
}

requiredAuth(config('security.use_auth'), config('security.user'), config('security.user_pass'), L::class);

return jsonResponse([
    "ngRebornVersion" => config('ngreborn.version'),
    "debug" => config('general.debug'),
    "mapCenter" => [$centre[0], $centre[1]],
    "mapDefaultZoom" => config('map.zoom'),
    "mapTiles" => (config('map.style') == "" ? "//{s}.tile.osm.org/{z}/{x}/{y}.png" : config('map.style')),
    "locale" => config('ngreborn.language'),
    "cbMode" => config('ngreborn.changes_bar_mode'),
    "cbSize" => config('ngreborn.changes_bar_size'),
    "cbFilter" => config('ngreborn.bar_filter'),
    "cbFontSize" => config('ngreborn.font_size'),
    "priorities" => [
        'unknown' => config('ngreborn.priorities.unknown'),
        'up' => config('ngreborn.priorities.up'),
        'warning' => config('ngreborn.priorities.warning'),
        'critical' => config('ngreborn.priorities.critical'),
        'down' => config('ngreborn.priorities.down')
    ],
    "soundAlert" => config('ngreborn.play_sound'),
    "iconStyle" => config('ngreborn.icon_style'),
    "showLines" => config('ngreborn.lines'),
    "updateTime" => config('ngreborn.time_update'),
    "secKey" => config('security.key'),
    "defaultAuth" => checkDefaultAuth(config('security.use_auth'), config('security.user'), config('security.user_pass')),
    "reporting" => config('ngreborn.reporting'),
    "domain" => config('ngreborn.domain'),
    "initialHosts" => $final_hosts,
    "translation" => json_decode(file_get_contents(NGR_DOCUMENT_ROOT .  "/langs/" . config('ngreborn.language') . ".json"))
]);

//Function to generate hash of files avoiding problems with encode.
// function fileHash($file)
// {
//     $data = file_get_contents($file);
//     $arr = explode(PHP_EOL, $data);
//     return md5(serialize($arr));
// }

// $checkFile = parse_ini_file("resources/checkFiles.ini");

// $ORIGINALFILES = "true";

// foreach ($checkFile as $key => $value) {
//     if (fileHash($key) != $value) {
//         $ORIGINALFILES = "false";
//         break;
//     }
// }

// define("NGR_VERSION", file_get_contents("VERSION")); //rand());