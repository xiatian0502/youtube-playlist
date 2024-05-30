<?php

$playlistIds = [
    'PLQWMqkNuwweK2NUFEex3Jked5lBWcUIJc&index=6',
    'PLNXJ_YC1PDA1L6H_ec0pn25QkDdK_8KrB',
    'PLrVKx-I9kvCRd869r_lVfnM6h4WHqdJe8',  
    'PLwACruPGUorXHJa2kX5Olh-YbKAb3rm-q',
    'PLQWMqkNuwweLu5hFpWCVHiRkdHhrHBpop',
    'PLg4glStCiHhrb425xNCsu2pfBJvpvBVok',
    'PLg4glStCiHhrICchD-I3W0z7FS7LEayu2',
    'PLwACruPGUorWsIwJA6UetUpPu4qi6aUV2',   
    'PLrVKx-I9kvCTNaiwtXI3_VujjhZRYxAlM',
    'PLrVKx-I9kvCQN_yVvm2afr0_e-ES5K2tr',
    'PLrVKx-I9kvCTNZ0jKMUOqtnn5wNk13_Rq',   
    'PLrVKx-I9kvCQ4gkJ-IGhhh2yZq38FobF8',   
    'PLrVKx-I9kvCRAVwkSfzjq_eZ66f89sz_h',    
    'PL0z1ZjXYEnlohS5MIDibsc1D8seHMwsXi',
    'PLiMeZxaUvGEJtAuzQMQxjSWJ6ueyEfTi4',
    'PLiMeZxaUvGEIZLpgh_9uZnDpSGp6Hg_61',
    'PLiMeZxaUvGEJtxtAyQVfkv_7QE9ttMNrz',    
    'PLiMeZxaUvGEKRMoNvlvuowilo93bbCHvh',    
    'PLPEKV0iMwYZLOkTmYFYxNzQnOo7aYzNiT',  
    'PLPEKV0iMwYZLIRBs8xSm9du4MoInhywy1',
    'PLPEKV0iMwYZLUxFvziNn-q1lrLtyZKz_F',    
    'PLOrDt87s8A3rMMqrz_Sz2kSt4lzWiPg4h',
    'PL0VLshn35eZIQ8eIvFcj0TiuiiVl08Jvf',
    'PLk3vQVLQ1uijSRpwOspimMhNAiT0qHUka'   
]; 

$maxResults = 30;
$API_keys = [
    'AIzaSyB8YDU9wXTKvEKeGcTUJ0_c-zTWOLR-W-Q',   
    'AIzaSyDQk7q3ofSUDpeNkg2FLOGktL_WxeohNQs',   
    'AIzaSyAJOoTUHOhlhNtXopmj69DxxIgXDsKoU-I',   
    'AIzaSyCbAAVySCRK-hxRk6dcfFC6g28_mLd1IU0',   
    'AIzaSyCtXmUFNlgVBOXW8XPjrXe8u71kBH_AjUw',  
    'AIzaSyDqzmFAvomLLZGg1WWbpdhm7It26HsVh_Q'
];

$categories = [];
$apiKeyIndex = 0;

function fetchWithRetry($url, $maxRetries = 5, $delay = 2) {
    $retryCount = 0;
    while ($retryCount < $maxRetries) {
        $response = file_get_contents($url);
        if ($response !== false) {
            return json_decode($response, true);
        }
        sleep($delay);  // 等待一段时间后重试
        $retryCount++;
    }
    return null;
}

$log = 'command_output.txt';
file_put_contents($log, "Start Execution\n", FILE_APPEND);

foreach ($playlistIds as $playlistId) {
    $apiKey = $API_keys[array_rand($API_keys)];
    $apiUrl = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $maxResults . '&playlistId=' . $playlistId . '&key=' . $apiKey;
    $videoList = fetchWithRetry($apiUrl);

    // 记录API请求和响应
    file_put_contents($log, "API Request: $apiUrl\nResponse: " . print_r($videoList, true) . "\n", FILE_APPEND);

    // 如果 API 调用失败，尝试下一个 API 密钥
    if (!$videoList || !isset($videoList['items'])) {
        $categories["未分类"][] = "#播放列表 " . $playlistId . " 未找到";
        continue;
    }

    foreach ($videoList['items'] as $item) {
        $youtubeUrl = 'https://www.youtube.com/watch?v=' . $item['snippet']['resourceId']['videoId'];
        $videoTitle = $item['snippet']['title'];
        $channelId = $item['snippet']['channelId'];

        // 获取频道名称
        $channelUrl = 'https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channelId . '&key=' . $apiKey;
        $channelInfo = fetchWithRetry($channelUrl);
        $channelTitle = isset($channelInfo['items'][0]['snippet']['title']) ? $channelInfo['items'][0]['snippet']['title'] : '未知频道';

        // 记录频道请求和响应
        file_put_contents($log, "Channel Request: $channelUrl\nResponse: " . print_r($channelInfo, true) . "\n", FILE_APPEND);

        $command = "/home/runner/.local/bin/yt-dlp -f best --get-url --no-playlist --no-warnings --force-generic-extractor --user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0' --youtube-skip-dash-manifest " . escapeshellarg($youtubeUrl);
        $streamUrl = shell_exec($command);

        // 记录yt-dlp命令和输出
        file_put_contents($log, "yt-dlp Command: $command\nOutput: $streamUrl\n", FILE_APPEND);

        if ($streamUrl !== null && strpos($streamUrl, 'http') === 0) {
            $streamUrl = trim($streamUrl);
        } else {
            $streamUrl = $youtubeUrl;  // 使用原始URL
        }

        if (!isset($categories[$channelTitle])) {
            $categories[$channelTitle] = [];
        }
        $categories[$channelTitle][] = "#EXTINF:-1 group-title=\"" . $channelTitle . "\"," . $videoTitle . PHP_EOL . $streamUrl . PHP_EOL;
    }
}

$file = fopen('playlist.m3u', 'w');
fwrite($file, "#EXTM3U" . PHP_EOL);

foreach ($categories as $category => $videos) {
    if (!empty($videos)) {
        foreach ($videos as $video) {
            fwrite($file, $video);
        }
    }
}

fclose($file);

file_put_contents($log, "End Execution\n", FILE_APPEND);
?>
