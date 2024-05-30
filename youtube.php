<?php

$playlistIds = [
    'PLQWMqkNuwweK2NUFEex3Jked5lBWcUIJc&index=6',
    'PLNXJ_YC1PDA1L6H_ec0pn25QkDdK_8KrB',
    'PLrVKx-I9kvCRd869r_lVfnM6h4WHqdJe8',  // 原有播放列表ID
    'PLwACruPGUorXHJa2kX5Olh-YbKAb3rm-q',   // 新添加的播放列表ID
    'PLQWMqkNuwweLu5hFpWCVHiRkdHhrHBpop',
    'PLg4glStCiHhrb425xNCsu2pfBJvpvBVok',    // 帕太
    'PLg4glStCiHhrICchD-I3W0z7FS7LEayu2',   // 帕太
    'PLwACruPGUorWsIwJA6UetUpPu4qi6aUV2',   // 帕太
    'PLrVKx-I9kvCTNaiwtXI3_VujjhZRYxAlM',    // 雷探长
    'PLrVKx-I9kvCQN_yVvm2afr0_e-ES5K2tr',     // 雷探长
    'PLrVKx-I9kvCTNZ0jKMUOqtnn5wNk13_Rq',    // 雷探长
    'PLrVKx-I9kvCQ4gkJ-IGhhh2yZq38FobF8',    // 雷探长
    'PLrVKx-I9kvCRAVwkSfzjq_eZ66f89sz_h',    // 雷探长
    'PL0z1ZjXYEnlohS5MIDibsc1D8seHMwsXi',    // 雷探长
    'PLiMeZxaUvGEJtAuzQMQxjSWJ6ueyEfTi4',      // 主播私享
    'PLiMeZxaUvGEIZLpgh_9uZnDpSGp6Hg_61',      // 主播私享
    'PLiMeZxaUvGEJtxtAyQVfkv_7QE9ttMNrz',     // 主播私享
    'PLiMeZxaUvGEKRMoNvlvuowilo93bbCHvh',     // 主播私享
    'PLPEKV0iMwYZLOkTmYFYxNzQnOo7aYzNiT',    // 老戴在此
    'PLPEKV0iMwYZLIRBs8xSm9du4MoInhywy1',   // 老戴在此
    'PLPEKV0iMwYZLUxFvziNn-q1lrLtyZKz_F',    // 老戴在此
    'PLOrDt87s8A3rMMqrz_Sz2kSt4lzWiPg4h',    // 李永乐老师
    'PL0VLshn35eZIQ8eIvFcj0TiuiiVl08Jvf',    // 橘子老师國外聲樂老師超真實銳評
    'PLk3vQVLQ1uijSRpwOspimMhNAiT0qHUka'    // 錫蘭Ceylan
];
$maxResults = 30;
$API_key = 'AIzaSyAONZd3f8TN6QZS39WCeddl7YqP1TdhkkQ'; 

$categories = [];

foreach ($playlistIds as $playlistId) {
    $videoList = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $maxResults . '&playlistId=' . $playlistId . '&key=' . $API_key), true);

    if (!$videoList || !isset($videoList['items'])) {
        $categories["未分类"][] = "#播放列表 " . $playlistId . " 未找到";
        continue;
    }

    foreach ($videoList['items'] as $item) {
        $youtubeUrl = 'https://www.youtube.com/watch?v=' . $item['snippet']['resourceId']['videoId'];
        $videoTitle = $item['snippet']['title'];
        $channelId = $item['snippet']['channelId'];

        // 获取频道名称
        $channelInfo = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channelId . '&key=' . $API_key), true);
        $channelTitle = isset($channelInfo['items'][0]['snippet']['title']) ? $channelInfo['items'][0]['snippet']['title'] : '未知频道';

        $command = "/home/runner/.local/bin/yt-dlp -f best --get-url --no-playlist --no-warnings --force-generic-extractor --user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0' --youtube-skip-dash-manifest " . escapeshellarg($youtubeUrl);
        $streamUrl = shell_exec($command);

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
?>
