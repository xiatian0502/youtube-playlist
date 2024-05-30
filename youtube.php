<?php

$playlistIds = [
    'PLQWMqkNuwweLu5hFpWCVHiRkdHhrHBpop',
    'PLg4glStCiHhrb425xNCsu2pfBJvpvBVok',    // 帕太
    'PLg4glStCiHhrICchD-I3W0z7FS7LEayu2',    // 帕太
    'PLwACruPGUorWsIwJA6UetUpPu4qi6aUV2',    // 帕太
    'PLrVKx-I9kvCTNaiwtXI3_VujjhZRYxAlM',    // 雷探长
    'PLrVKx-I9kvCQN_yVvm2afr0_e-ES5K2tr',    // 雷探长
    'PLrVKx-I9kvCTNZ0jKMUOqtnn5wNk13_Rq',    // 雷探长
    'PLrVKx-I9kvCQ4gkJ-IGhhh2yZq38FobF8',    // 雷探长
    'PLrVKx-I9kvCRAVwkSfzjq_eZ66f89sz_h',    // 雷探长
    'PL0z1ZjXYEnlohS5MIDibsc1D8seHMwsXi',    // 雷探长
    'PLiMeZxaUvGEJtAuzQMQxjSWJ6ueyEfTi4',    // 主播私享
    'PLiMeZxaUvGEIZLpgh_9uZnDpSGp6Hg_61',    // 主播私享
    'PLiMeZxaUvGEJtxtAyQVfkv_7QE9ttMNrz',    // 主播私享
    'PLiMeZxaUvGEKRMoNvlvuowilo93bbCHvh',    // 主播私享
    'PLPEKV0iMwYZLOkTmYFYxNzQnOo7aYzNiT',    // 老戴在此
    'PLPEKV0iMwYZLIRBs8xSm9du4MoInhywy1',    // 老戴在此
    'PLPEKV0iMwYZLUxFvziNn-q1lrLtyZKz_F',    // 老戴在此
    'PLOrDt87s8A3rMMqrz_Sz2kSt4lzWiPg4h',    // 李永乐老师
    'PL0VLshn35eZIQ8eIvFcj0TiuiiVl08Jvf',    // 橘子老师国 外声乐老师超真实锐评
    'PLk3vQVLQ1uijSRpwOspimMhNAiT0qHUka'     // 锡兰Ceylan
];

$maxResults = 20;
$API_keys = [
    'AIzaSyAONZd3f8TN6QZS39WCeddl7YqP1TdhkkQ',
    'AIzaSyB8YDU9wXTKvEKeGcTUJ0_c-zTWOLR-W-Q',   
    'AIzaSyDQk7q3ofSUDpeNkg2FLOGktL_WxeohNQs',   
    'AIzaSyAJOoTUHOhlhNtXopmj69DxxIgXDsKoU-I',   
    'AIzaSyCbAAVySCRK-hxRk6dcfFC6g28_mLd1IU0',   
    'AIzaSyCtXmUFNlgVBOXW8XPjrXe8u71kBH_AjUw',  
    'AIzaSyDqzmFAvomLLZGg1WWbpdhm7It26HsVh_Q'
];

$categories = [];
$apiKeyIndex = 0;
$apiKeyCount = count($API_keys);

function getAPIKey(&$apiKeyIndex, $apiKeyCount, $API_keys) {
    $apiKey = $API_keys[$apiKeyIndex];
    $apiKeyIndex = ($apiKeyIndex + 1) % $apiKeyCount;
    return $apiKey;
}

foreach ($playlistIds as $playlistId) {
    do {
        $API_key = getAPIKey($apiKeyIndex, $apiKeyCount, $API_keys);
        $api_url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $maxResults . '&playlistId=' . $playlistId . '&key=' . $API_key;
        $videoListResponse = @file_get_contents($api_url);
        
        if ($videoListResponse === FALSE) {
            sleep(1); // 如果调用失败，等待1秒再重复
        }
    } while ($videoListResponse === FALSE);

    $videoList = json_decode($videoListResponse, true);

    // 如果API请求失败或没有找到视频项，添加未分类信息并跳过该播放列表
    if (!$videoList || !isset($videoList['items'])) {
        $categories["未分类"][] = "播放列表 " . $playlistId . " 未找到";
        continue;
    }

    foreach ($videoList['items'] as $item) {
        $youtubeUrl = 'https://www.youtube.com/watch?v=' . $item['snippet']['resourceId']['videoId'];
        $videoTitle = $item['snippet']['title'];
        $channelId = $item['snippet']['channelId'];

        do {
            $API_key = getAPIKey($apiKeyIndex, $apiKeyCount, $API_keys);
            $channelInfoResponse = @file_get_contents('https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channelId . '&key=' . $API_key);
            
            if ($channelInfoResponse === FALSE) {
                sleep(1); // 如果调用失败，等待1秒再重复
            }
        } while ($channelInfoResponse === FALSE);

        $channelInfo = json_decode($channelInfoResponse, true);
        $channelTitle = isset($channelInfo['items'][0]['snippet']['title']) ? $channelInfo['items'][0]['snippet']['title'] : '未知频道';

        $command = "/home/runner/.local/bin/yt-dlp -f best --get-url --no-playlist --no-warnings --force-generic-extractor --user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0' --youtube-skip-dash-manifest " . escapeshellarg($youtubeUrl);
        
        do {
            $streamUrl = shell_exec($command);
            if ($streamUrl !== null && strpos($streamUrl, 'http') === 0) {
                $streamUrl = trim($streamUrl);
            } else {
                sleep(1); // 如果调用失败，等待1秒再重复
            }
        } while (!isset($streamUrl) || strpos($streamUrl, 'http') !== 0);

        if (!isset($categories[$channelTitle])) {
            $categories[$channelTitle] = [];
        }
        $categories[$channelTitle][] = "#EXTINF:-1 group-title=\"" . $channelTitle . "\"," . $videoTitle . PHP_EOL . $streamUrl . PHP_EOL;

        // 延时以降低API调用频率，减少被限制的可能性
        usleep(250000); // 250毫秒
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

?>
