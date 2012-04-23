#!/usr/bin/php
<?php
include_once "config.php";
error_reporting(0);

$conn=mysql_connect($mysql_host,$mysql_user,$mysql_passwd);
if(!mysql_select_db("4sq",$conn)){
    $error_string .= "ERROR: can't connect to the DB\n";
    print ("$error_string");
    exit(1);
}

function random_float ($min,$max) {
   return ($min+lcg_value()*(abs($max-$min)));
}

function fsq_venue_details($venueId) {
    global $oauth_token;
    global $app_version;
    $fields_string = "?";
    $url = "https://api.foursquare.com/v2/venues/$venueId";
    $fields = array(
        'oauth_token' => urlencode($oauth_token),
        'v' => urlencode($app_version)
    );
    foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    rtrim($fields_string,'&');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result; # json
}

function fsq_search($lat,$lng) {
    global $oauth_token;
    global $app_version;
    global $radius;
    $fields_string = "?";
    $url = "https://api.foursquare.com/v2/venues/search";
    $fields = array(
        'oauth_token' => urlencode($oauth_token),
        'll' => urlencode($lat).",".urlencode($lng),
        'radius' => urlencode($radius),
        'intent' => urlencode("browse"),
#        'intent' => urlencode("checkin"),  #default
        'limit' => urlencode(50),
        'v' => urlencode($app_version)
    );
    foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    rtrim($fields_string,'&');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result; # json
}

function fsq_check_checkin_results($json) {
    global $my_id;
    $data = json_decode($json);
    $isMayor = $data->response->checkin->isMayor;
    $id = $data->response->checkin->venue->id;
    $name = $data->response->checkin->venue->name;
    if ($isMayor == 'true') {
            print " MAYOR";
        mysql_query("update venues set mayorid = '$my_id' where id = '$id'");
    }
    $msg = "";
    foreach ($data->notifications as $notification) {
        if ($notification->type == 'mayorship') {
            $msg .= "\t".$notification->item->message."\n";
        }
        if ($notification->type == 'message') {
            $msg .= "\t".$notification->item->message."\n";
        }
    }
    return($msg);
}

function fsq_checkin($venueId,$lat=0.0,$lng=0.0) {
    global $oauth_token;
    global $app_version;
    $fields_string = "";
    $url = "https://api.foursquare.com/v2/checkins/add";
    if ($lat == 0.0) {
        $fields = array(
            'oauth_token' => urlencode($oauth_token),
            'venueId' => urlencode($venueId),
            'broadcast' => urlencode('public'),
            'v' => urlencode($app_version)
        );
    } else {
        $fields = array(
            'oauth_token' => urlencode($oauth_token),
            'venueId' => urlencode($venueId),
            'broadcast' => urlencode('public'),
            'll' => "$lat,$lng",
            'v' => urlencode($app_version)
        );
    }
    foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    rtrim($fields_string,'&');
    print ("Checking into: $venueId");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    print " EMAILING";
    mail('bigwebb@gmail.com', "[4sq checkin] $venueId", $result);
    $msg = fsq_check_checkin_results($result);
    print " DONE\n";
    if ($msg != "") {}
        print "$msg\n";
    }
    sleep(3);
    return $result; # json
}

function fsq_venue_to_mysql($id,$name,$usersCount,$checkinsCount,$lat,$lng,$address,$city,$state,$postalCode) {
    $result = mysql_query("insert into venues values ('$id','$name','$usersCount','$checkinsCount','$lat','$lng','$address','$city','$state','$postalCode','','',NULL,NULL,NULL,NULL)");
    if (!$result) {
            mysql_query("update venues set usersCount = '$usersCount', checkinsCount = '$checkinsCount' where id = '$id'");
    }
}

function fsq_venue_parse($json) {
    $data = json_decode($json);
    $mayorId = $data->response->venue->mayor->user->id;
    $mayorCount = $data->response->venue->mayor->count;
    $id = $data->response->venue->id;
    mysql_query("update venues set mayorId = '$mayorId', mayorCount = '$mayorCount', m_lastVenueDetails = now() where id = '$id'");
    print ("$id $mayorId $mayorCount\n");
}

function fsq_search_parse($json) {
    $data = json_decode($json);
    foreach ($data->response->venues as $venue) {
        $id = $venue->id;
        $name = $venue->name;
        $usersCount = $venue->stats->usersCount;
        $checkinsCount = $venue->stats->checkinsCount;
        $lat = $venue->location->lat;
        $lng = $venue->location->lng;
        $address = $venue->location->address;
        $city = $venue->location->city;
        $state = $venue->location->state;
        $postalCode = $venue->location->postalCode;
        fsq_venue_to_mysql($id,$name,$usersCount,$checkinsCount,$lat,$lng,$address,$city,$state,$postalCode);
        print ("inserting: $name\n");
    }
}

function fsq_pull_all_venue_details() {
    $result = mysql_query("select * from venues where mayorId = 0 and m_lastVenueDetails is NULL");
    while ($row = mysql_fetch_assoc($result)) {
        $id = $row['id'];
        $response = fsq_venue_details($id);  # deli zone
        fsq_venue_parse($response);
    }
}

function fsq_search_for_more_venues() {
    $result = mysql_query("select * from venues where m_lastSearch is NULL");
    while ($row = mysql_fetch_assoc($result)) {
        $lat = $row['lat'];
        $lng = $row['lng'];
        $response = fsq_search($lat,$lng);  #
        fsq_search_parse($response);
        mysql_query("update venues set m_lastSearch = now() where lat = '$lat' and lng = '$lng'");
    }
}

function find_checkin_to_pri1_venues() {
    global $checkin_limit;
    $num_checkins = 0;
    $result = mysql_query("select * from venues where m_checkinpriority is not null and (m_lastCheckin <= date_sub(now(), INTERVAL 15 hour) or m_lastCheckin is null) order by `m_checkinPriority` limit $checkin_limit");
    while ($row = mysql_fetch_assoc($result)) {
        $id = $row['id'];
        $lat = $row['lat'];
        $lng = $row['lng'];
        $new_lat = random_float($lat-0.0005,$lat+0.0005);
        $new_lng = random_float($lng-0.0005,$lng+0.0005);
        $json = fsq_checkin($id,$new_lat,$new_lng);
        $result2 = mysql_query("update venues set m_lastCheckin = now() where id = '$id'");
        $num_checkins++;
    }
    return $num_checkins;
}

function find_checkin_to_pri2_venues() {
    global $checkin_limit;
    global $my_id;
    $num_checkins = 0;
    $result = mysql_query("select * from venues where m_checkinpriority is null and (m_lastCheckin is null or m_lastCheckin <= date_sub(now(), INTERVAL 15 hour)) and mayorid != '$my_id' order by mayorCount limit $checkin_limit");
    while ($row = mysql_fetch_assoc($result)) {
        $id = $row['id'];
        $lat = $row['lat'];
        $lng = $row['lng'];
        $new_lat = random_float($lat-0.0005,$lat+0.0005);
        $new_lng = random_float($lng-0.0005,$lng+0.0005);
        $json = fsq_checkin($id,$new_lat,$new_lng);
        $result2 = mysql_query("update venues set m_lastCheckin = now() where id = '$id'");
        $num_checkins++;
    }
    return $num_checkins;
}

date_default_timezone_set('America/Denver');
$thedate = date('l jS \of F Y h:i:s A');
print ("$thedate\n");

if ($argv[1] == 'steal') {
    find_checkin_to_pri1_venues();
}

if ($argv[1] == 'checkin') {
    find_checkin_to_pri2_venues();
}

if ($argv[1] == 'search') {
    fsq_search_for_more_venues();
}

if ($argv[1] == 'scan') {
    fsq_pull_all_venue_details();
}

if ($argv[1] == 'rescan') {
    $result = mysql_query("update venues set mayorId = 0, `m_lastVenueDetails` = NULL");
    fsq_pull_all_venue_details();
}

#fsq_check_checkin_results();

#find_checkin_to_pri1_venues();
#find_checkin_to_pri2_venues();

#$response = fsq_venue_details("4b4292acf964a520cfd625e3");  # deli zone
#$response = fsq_venue_details("49d660a9f964a520be5c1fe3");  # deli zone
#fsq_venue_parse($response);

#$response = fsq_search(40.018356,-105.277497);  #
#fsq_search_parse($response);

#$response = fsq_checkin("4b4292acf964a520cfd625e3");  # deli zone
#print_r("$response\n");

?>