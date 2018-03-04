<?php
include("functions.php");
// pre-define variables so the E_NOTICES do not show in webserver logs
$javascript = "";

// Get list of all Nagios configuration files into an array
$files = get_config_files();

// Read content of all Nagios configuration files into one huge array
foreach ($files as $file) {
  $raw_data[$file] = file($file);
}

$data = filter_raw_data($raw_data);

// hosts definition - we are only interested in hostname, parents and notes with position information
foreach ($data as $host) {
  if (((!empty($host["host_name"])) && (!preg_match("/^\\!/", $host['host_name']))) | ($host['register'] == 0)) {
    $hostname = 'x'.safe_name($host["host_name"]).'x';
    $hosts[$hostname]['host_name'] = $hostname;
    $hosts[$hostname]['nagios_host_name'] = $host["host_name"];
    $hosts[$hostname]['alias'] = $host["alias"];

    // iterate for every option for the host
    foreach ($host as $option => $value) {
      // get parents information
      if ($option == "parents") {
        $parents = explode(',', $value); 
        foreach ($parents as $parent) {
          $parent = safe_name($parent);
          $hosts[$hostname]['parents'][] = "x".$parent."x";
        }
        continue;
      }
      // we are only interested in latlng values from notes
      if ($option == "notes") {
        if (preg_match("/latlng/",$value)) { 
          $value = explode(":",$value); 
          $hosts[$hostname]['latlng'] = trim($value[1]);
          continue;
        } else {
          continue;
        }
      };
      // another few information we are interested in
      if (($option == "address")) {
        $hosts[$hostname]['address'] = trim($value);
      };
      if (($option == "hostgroups")) {
        $hostgroups = explode(',', $value);
        foreach ($hostgroups as $hostgroup) {
          $hosts[$hostname]['hostgroups'][] = $hostgroup;
        }
      };
      // another few information we are interested in - this is a user-defined nagios variable
      if (preg_match("/^_/", trim($option))) {
        $hosts[$hostname]['user'][] = $option.':'.$value;
      };
      unset($parent, $parents);
    } 
  }
}
unset($data);

if ($nagMapR_filter_hostgroup) {
  foreach ($hosts as $host) {
    if (!in_array($nagMapR_filter_hostgroup, $hosts[$host["host_name"]]['hostgroups'])) {
      unset($hosts[$host["host_name"]]);
    }
  }
}

// get host statuses
$s = nagMapR_status();
// remove hosts we are not able to render and combine those we are able to render with their statuses 
foreach ($hosts as $h) {
  if ((isset($h["latlng"])) AND (isset($h["host_name"])) AND (isset($s[$h["nagios_host_name"]]['status']))) {
    $data[$h["host_name"]] = $h;
    $data[$h["host_name"]]['status'] = $s[$h["nagios_host_name"]]['status'];
    $data[$h["host_name"]]['status_human'] = $s[$h["nagios_host_name"]]['status_human'];
    $data[$h["host_name"]]['status_style'] = $s[$h["nagios_host_name"]]['status_style'];
  } else {
    if ($nagMapR_debug) { 
      echo('// '.$ignoredHosts.$h['host_name'].":".$h['latlng'].":".$s[$h["nagios_host_name"]]['status_human'].":\n");
    }
  }
}
unset($hosts);
unset($s);

$ii = 0;

foreach($data as $h) {
  $jsData[$ii] = $h;
  $ii++;
}

//$javascript .= ("var MARK = [".($ii - 1)."];\n");
$ii = 0;
// put markers and bubbles onto a map
foreach ($data as $h) {
  if ($nagMapR_debug) {
    echo('<!--'.$positionHosts.$h['host_name'].":".$h['latlng'].":".$h['status'].":".$h['status_human']."-->\n");
  }
    // position the host on the map
  $javascript .= ("window.".$h["host_name"]."_pos = new google.maps.LatLng(".$h["latlng"].");\n");

    // display different icons for the host (according to the status in nagios)
    // if host is in state OK
  if ($h['status'] == 0) {
    $javascript .= ("MARK.push(new google.maps.Marker({".
      "\n  position: ".$h["host_name"]."_pos,".
      "\n  icon: 'icons/marker_green.png',".
      "\n  map: map,".
      "\n  zIndex: 2,".
      "\n  title: \"".$h["nagios_host_name"]."\"".
      "}));"."\n\n");
    // if host is in state WARNING
  } elseif ($h['status'] == 1) {
    $javascript .= ("MARK.push(new google.maps.Marker({".
      "\n  position: ".$h["host_name"]."_pos,".
      "\n  icon: 'icons/marker_yellow.png',".
      "\n  map: map,".
      "\n  zIndex: 3,".
      "\n  title: \"".$h["nagios_host_name"]."\"".
      "}));"."\n\n");
    // if host is in state CRITICAL / UNREACHABLE
  } elseif ($h['status'] == 2) {
    $javascript .= ("MARK.push(new google.maps.Marker({".
      "\n  position: ".$h["host_name"]."_pos,".
      "\n  icon: 'icons/marker.png',".
      "\n  map: map,".
      "\n  zIndex: 4,".
      "\n  title: \"".$h["nagios_host_name"]."\"".
      "}));"."\n\n");
    // if host is in state UNKNOWN
  } else {
    // if host is in any other (unknown to nagMapR) state
    $javascript .= ("window.MARK.push(new google.maps.Marker({".
      "\n  position: ".$h["host_name"]."_pos,".
      "\n  icon: 'icons/marker_grey.png',".
      "\n  map: map,".
      "\n  zIndex: 6,".
      "\n  title: \"".$h["nagios_host_name"]."\"".
      "}));"."\n\n");
  };
    //generate google maps info bubble
  if (!isset($h["parents"])) { $h["parents"] = Array(); }; 
  $info = '<div class=\"bubble\"><strong>'.$h["nagios_host_name"]."</strong><br><table>"
  .'<tr><td>'.$alias.'</td><td>:</td><td> '.$h["alias"].'</td></tr>'
  .'<tr><td>'.$hostG.'</td><td>:</td><td> '.join('<br>', $h["hostgroups"]).'</td></tr>'
  .'<tr><td>'.$addr.'</td><td>:</td><td> '.$h["address"].'</td></tr>'
  .'<tr><td>'.$other.'</td><td>:</td><td> '.join("<br>",$h['user']).'</td></tr>'
  .'<tr><td>'.$hostP.'</td><td>:</td><td> '.join('<br>' , $h["parents"]).'</td></tr>'
  .'</table>'
  .'<a href=\"/nagios/cgi-bin/statusmap.cgi\?host='.$h["nagios_host_name"].'\">Nagios map page</a>'
  .'<br><a href=\"/nagios/cgi-bin/extinfo.cgi\?type=1\&host='.$h["nagios_host_name"].'\">Nagios host page</a>';

  $javascript .= ("window.".$h["host_name"]."_mark_infowindow = new google.maps.InfoWindow({ content: '$info'})\n");

  $javascript .= ("google.maps.event.addListener(MARK[".$ii."], 'click', function() {"
    .$h["host_name"]."_mark_infowindow.open(map, MARK[".$ii."]);\n
  });\n\n");
  $ii++;
};

$ii = 0;

// create (multiple) parent connection links between nodes/markers
$javascript .= "// generating links between hosts\n";
foreach ($data as $h) {
  // if we do not have any parents, just create an empty array
  if (!isset($h["latlng"]) OR (!is_array($h["parents"]))) {
    continue;
  }
  foreach ($h["parents"] as $parent) {
    if (isset($data[$parent]["latlng"])) {
      // default colors for links
      $stroke_color = "#59BB48";
      // links in warning state
      if ($h['status'] == 1) { $stroke_color ='#ffff00'; }
      // links in problem state
      if ($h['status'] == 2) { $stroke_color ='#ff0000'; }
      $javascript .= "\n";

      $linesArray .= ("LINES.push({line: null, host:\"".$h["host_name"]."\", parent:\"".$parent."\"});\n");

      $javascript .= ('LINES['.$ii.'].line = new google.maps.Polyline({'."\n".
        ' path: ['.$h["host_name"].'_pos,'.$parent.'_pos],'."\n".
        "  strokeColor: \"$stroke_color\",\n".
        "  strokeOpacity: 0.9,\n".
        "  strokeWeight: 2});\n");
      $javascript .= ('LINES['.$ii."].line.setMap(map);\n\n");
      $ii++;
    }
  }
}

?>