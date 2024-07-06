<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo 'Only POST requests are allowed';
    return;
}
if (!array_key_exists('channel', $_REQUEST)) {
    echo 'Empty channel';
    return;
}
if (!array_key_exists('action', $_REQUEST)) {
    echo 'Empty action (get/update)';
    return;
}

$channel = htmlspecialchars($_REQUEST['channel']);   // Sanitize input
$file = "{$channel}.watchtime.json";    // File with watchtime

// Open json and collect data
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'Error reading watchtime data';
        return;
    }
} else {
    $data = [];
}

// Ensure $data is an array
if (!is_array($data)) {
    $data = [];
}


// Ensure the epoch time of the last update is set
if (!isset($data['$'])) {
    $data['$'] = time(); // Initialize to the current time if it does not exist
}

if ($_REQUEST['action'] == 'update') {
    $now = time();  // Epoch time
    if (array_key_exists('$', $data) && $now - $data['$'] < 600) {
        // Increment if only the last update has been made in less than 10 min.
        $postData = json_encode([
            'query' => '{user(login: "' . $channel . '")  { channel { chatters  { count } } } }'
        ]);

        $ModsData = json_encode([
            'query' => '{user(login: "' . $channel . '")  { channel { chatters  { moderators { login { } } } } } }'
        ]);

        $viewerCountData = json_encode([
            'query' => '{user(login: "' . $channel . '") { stream { viewersCount } } }'
        ]);

        $vipsData = json_encode([
            'query' => '{user(login: "' . $channel . '")  { channel { chatters  { vips { login { } } } } } }'
        ]);

        $viewersData = json_encode([
            'query' => '{user(login: "' . $channel . '")  { channel { chatters  { viewers { login { } } } } } }'
        ]);

        

        // Fetch moderators
        $url = "https://gql.twitch.tv/gql";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL certificate verification
        curl_setopt($ch, CURLOPT_CAINFO, 'cacert'); // Specify path to CA certificates bundle
        #curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $ModsData);
        #curl_setopt($ch, CURLOPT_POSTFIELDS, $viewerCountData);
        #curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            #'Authorization: Bearer 2248suncki9npc4dqrhl9hvvfzzdaf', // Replace with your actual access token
            'Client-Id: kimne78kx3ncx6brgo4mv6wki5h1ko' // Replace with your Twitch client ID
        ]);

        $response = curl_exec($ch);

        // Check for errors
        if ($response === false) {
            echo 'Curl error: ' . curl_error($ch);
            curl_close($ch);
            exit;
        }

        // Check HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            echo "HTTP status code: $http_code\n";
            echo "Response: $response";
            curl_close($ch);
            exit;
        }

        // Close curl session
        curl_close($ch);

        // Process $response data (JSON decoding, etc.)
        #echo "Response from Twitch API:\n";
        #var_dump($response);

        $user = json_decode($response, true);

        // Debug: Print the raw response
        echo "<pre>";
        #print_r($data);
        echo "</pre>";

        // Check if the response contains the expected data structure
        if (isset($user['data']['user']['channel']['chatters']['moderators'])) {
            // Extract the chatters count
            $mods = $user['data']['user']['channel']['chatters']['moderators'];
            if (is_array($mods)) {
                echo "Moderators: <br>";
                foreach ($mods as $mod) {
                    #echo $mod['login'] . "<br>";
                }
            } else {
                echo "No mods listed";
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        if (empty($mods) || !isset($mods)) {
            echo "\nNo MOD data found";
            return;
        }
        // Check if streamer is live
        $url = "https://gql.twitch.tv/gql";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL certificate verification
        curl_setopt($ch, CURLOPT_CAINFO, 'cacert'); // Specify path to CA certificates bundle
        curl_setopt($ch, CURLOPT_POSTFIELDS, $viewerCountData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Client-Id: kimne78kx3ncx6brgo4mv6wki5h1ko' // Replace with your Twitch client ID
        ]);

        $response1 = curl_exec($ch);
        $user1 = json_decode($response1, true);

        // Debug: Print the raw response
        echo "<pre>";
        #print_r($user1);
        echo "</pre>";


        // Lazy way to find if the stream is off
        if (!is_array($user1["data"]["user"]["stream"])) {
            echo "\nNot Online";
            return;
        } else {
            echo "\nStreamer is online";
        };


        // Check for VIPS
        $url = "https://gql.twitch.tv/gql";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL certificate verification
        curl_setopt($ch, CURLOPT_CAINFO, 'cacert'); // Specify path to CA certificates bundle
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vipsData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Client-Id: kimne78kx3ncx6brgo4mv6wki5h1ko' // Replace with your Twitch client ID
        ]);

        $response2 = curl_exec($ch);
        $user2 = json_decode($response2, true);
        // Check if the response contains the expected data structure
        if (isset($user2['data']['user']['channel']['chatters']['vips'])) {
            // Extract the chatters count
            $vips = $user2['data']['user']['channel']['chatters']['vips'];
            if (is_array($mods)) {
                echo "<br>VIPS: <br>";
                foreach ($vips as $vip) {
                    #echo $vip['login'] . "<br>";
                }
            } else {
                echo "No mods listed";
            }
        } 

        // Check for VIEWERS
        $url = "https://gql.twitch.tv/gql";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL certificate verification
        curl_setopt($ch, CURLOPT_CAINFO, 'cacert'); // Specify path to CA certificates bundle
        curl_setopt($ch, CURLOPT_POSTFIELDS, $viewersData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Client-Id: kimne78kx3ncx6brgo4mv6wki5h1ko' // Replace with your Twitch client ID
        ]);

        $response3 = curl_exec($ch);
        $user3 = json_decode($response3, true);
        // Check if the response contains the expected data structure
        if (isset($user3['data']['user']['channel']['chatters']['viewers'])) {
            // Extract the chatters count
            $viewers = $user3['data']['user']['channel']['chatters']['viewers'];
            if (is_array($viewers)) {
                echo "<br>VIEWERS: <br>";
                foreach ($viewers as $viewer) {
                    #echo $viewer['login'] . "<br>";
                }
            } else {
                echo "No viewers listed";
            }
        }         


        // This script selects VIPS MODS VIEWERS
        $chatters = array_merge($mods,$vips,$viewers);
        // Increment watchtime
        $passed = $now - $data['$'];  // Time passed since last update
        foreach ($chatters as $viewer) {
            print_r($viewer['login']); 
            if (!array_key_exists($viewer['login'], $data)) {
                $data[$viewer['login']] = 0;
            }
            $data[$viewer['login']] += $passed;
        }
    }
    $data['$'] = $now;  // Store the epoch time of the update
    file_put_contents($file, json_encode($data));    // Save data
    echo "Finished";


///////GET USER DATA/////////////    
} elseif ($_REQUEST['action'] == 'get') {
    if (empty($data)) {
        echo 'Empty watchtime, update it first!';
        return;
    }
    if (!array_key_exists('user', $_REQUEST)) {
        echo 'Empty username';
        return;
    }
    $username = htmlspecialchars($_REQUEST['user']); // Sanitize input
    if (array_key_exists($username, $data)) {
        $passed = time() - $data['$'];
        if ($passed > 600) {
            $passed = 0;
        }
        $s = $data[$username] + $passed;

        $m = intdiv($s, 60);
        $s -= $m * 60;
        $h = intdiv($m, 60);
        $m -= $h * 60;
        $d = intdiv($h, 24);
        $h -= $d * 24;

        $args = [];
        if ($d > 0) array_push($args, "{$d} days");
        if ($h > 0) array_push($args, "{$h} hours");
        if ($m > 0) array_push($args, "{$m} minutes");
        if ($s > 0) array_push($args, "{$s} seconds");

        echo $username . ' watched the stream for ' . implode(', ', $args) . '!';
    } else {
        echo 'Invalid username "' . $username . '": moderator, too new or nonexistent';
    }
} else {
    echo 'Invalid action';
}
