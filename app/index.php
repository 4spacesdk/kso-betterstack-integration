<?php

// Google Health Checks and Kube Probe must return 200 on /
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $isGoogleHealthCheck = strpos($_SERVER['HTTP_USER_AGENT'], 'GoogleHC') !== false;
    $isKubeProbe = strpos($_SERVER['HTTP_USER_AGENT'], 'kube-probe') !== false;
    if ($isGoogleHealthCheck || $isKubeProbe) {
        http_response_code(200);
        exit;
    }
}

$ksoURL = getenv('KSO_BASE_URL');
$ksoClientId = getenv('KSO_CLIENT_ID');
$ksoClientSecret = getenv('KSO_CLIENT_SECRET');
$ksoLabelName = getenv('KSO_DEPLOYMENT_LABEL_NAME');
$ksoLabelValue = getenv('KSO_DEPLOYMENT_LABEL_VALUE');
$betterstackApiToken = getenv('BETTERSTACK_API_TOKEN');
$betterstackMonitorGroupName = getenv('BETTERSTACK_MONITOR_GROUP_NAME');

if (!$ksoURL || strlen($ksoURL) == 0) {
    return;
}

// Get KSO Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ksoURL . '/api/token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => $ksoClientId,
    'client_secret' => $ksoClientSecret,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$raw = curl_exec($ch);
$response = json_decode($raw);
curl_close($ch);
$ksoAccessToken = $response->access_token;

// Get KSO Workspaces
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer $ksoAccessToken"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, $ksoURL . '/api/workspaces?' . http_build_query([
        'include' => 'deployment'
    ]));
$raw = curl_exec($ch);
$response = json_decode($raw);
curl_close($ch);

// Get active deployment url's
$deploymentUrls = [];
foreach ($response->resources as $workspace) {
    foreach ($workspace->deployments as $deployment) {
        $isActive = $deployment->status == 'active';
        $hasLabel = false;
        foreach ($deployment->labels ?? [] as $label) {
            if ($label->name == $ksoLabelName && $label->value == $ksoLabelValue) {
                $hasLabel = true;
            }
        }

        if ($isActive && $hasLabel) {
            $deploymentUrls[] = $deployment->url_external;
        }
    }
}

// Create monitor group if not found
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer $betterstackApiToken"]);
curl_setopt($ch, CURLOPT_URL, 'https://uptime.betterstack.com/api/v2/monitor-groups');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$raw = curl_exec($ch);
$response = json_decode($raw);
curl_close($ch);
$hasGroup = false;
$groupId = null;
foreach ($response->data as $group) {
    if ($group->attributes->name == $betterstackMonitorGroupName) {
        $hasGroup = true;
        $groupId = $group->id;
        break;
    }
}
if (!$hasGroup) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer $betterstackApiToken"]);
    curl_setopt($ch, CURLOPT_URL, 'https://uptime.betterstack.com/api/v2/monitor-groups');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'name' => $betterstackMonitorGroupName,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    $response = json_decode($raw);
    curl_close($ch);
    $groupId = $response->data->id;
}

// List existing monitors
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer $betterstackApiToken"]);
curl_setopt($ch, CURLOPT_URL, "https://uptime.betterstack.com/api/v2/monitor-groups/{$groupId}/monitors");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$raw = curl_exec($ch);
$response = json_decode($raw);
curl_close($ch);

// Loop existing monitors and remove from $deploymentUrls
// Delete monitor if not found in $deploymentUrls
foreach ($response->data as $monitor) {
    if (($key = array_search($monitor->attributes->url, $deploymentUrls)) !== false) {
        unset($deploymentUrls[$key]);
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer $betterstackApiToken"]);
        curl_setopt($ch, CURLOPT_URL, "https://uptime.betterstack.com/api/v2/monitors/{$monitor->id}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $raw = curl_exec($ch);
        $response = json_decode($raw);
        curl_close($ch);
    }
}

// Create monitors not found already
foreach ($deploymentUrls as $deploymentUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer $betterstackApiToken"]);
    curl_setopt($ch, CURLOPT_URL, 'https://uptime.betterstack.com/api/v2/monitors');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'monitor_type' => 'status',
        'url' => $deploymentUrl,
        'email' => true,
        'check_frequency' => 3 * 60,
        'monitor_group_id' => $groupId,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    $response = json_decode($raw);
    curl_close($ch);
}



$data = [
    $deploymentUrls,
    $response,
    $hasGroup,
    $groupId
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
