<?php

$playlistIds = [
    'PLQWMqkNuwweK2NUFEex3Jked5lBWcUIJc&index=6',
    'PLrVKx-I9kvCRAVwkSfzjq_eZ66f89sz_h',
    'PLrVKx-I9kvCQ4gkJ-IGhhh2yZq38FobF8',
    'PLrVKx-I9kvCTNaiwtXI3_VujjhZRYxAlM',
    'PLrVKx-I9kvCTT2yZaZP32LcNkza2NjrEg',
    'PLrVKx-I9kvCTibDObcPz6BiUwtcv66jzM',
    'PLrVKx-I9kvCQTawi_6WuX5y-kz0Xx2iC9',
    'PLrVKx-I9kvCRspO_5dqtZjYWmHsCMxYWV',
    'PLg4glStCiHhrb425xNCsu2pfBJvpvBVok',
    'PLg4glStCiHhrICchD-I3W0z7FS7LEayu2',
    'PLk3vQVLQ1uijSRpwOspimMhNAiT0qHUka',
    'PLpXA7u6Y4dGeBwrabbPCRYyGPJp-ysVmw',
    'PLh9lJwqeOuvN3Thq2TaIOwJvyQniZt9l0',
    'PLcQH5CCDoZ4-YPhda-aGyzaaWvMFsLooH',
    'PLcQH5CCDoZ48NOddEhDQtajOeQqvdadoJ',
    'PLPEKV0iMwYZLOkTmYFYxNzQnOo7aYzNiT',
    'PLPEKV0iMwYZIJEmmKz7MQKEaZyfPlyDHH',
    'PLPEKV0iMwYZLxCV3CxQWEZaMZr6QuTsgw',
    'PLMUs_BF93V5aqfoGAI5Jh05yscmKegN0V',
    'PLMUs_BF93V5YW4tbdZbBkcNsD3yU2shL0',
    'PLOrDt87s8A3r4X6gzfA2wIwqmARLcYMXw',
    'PLOrDt87s8A3oV_INolHOLEyD8Sp6XcMFa',
    'PLOrDt87s8A3rTu_XAg7GZODkRA_FqoOm7',
    'PLp7hnLHxd1KFM279kSSuitl1LZr-rk3O6',
    'PLp7hnLHxd1KEyrBXOq2y9QLGACMcttfZH',
    'PLp7hnLHxd1KGYED9S8tQmXfXTLE04euZV',
    'PLvHT0yeWYIuAj5owXnEm0kbext46i8CJi',
    'PLD0nIS14ohhcGG2fhGyvr8y6qhGQqwYGL',
    'PL0z1ZjXYEnlohS5MIDibsc1D8seHMwsXi',
    'PLXDl0OrlJGsh7UhLSyKQdL1RZcMMbaJxL' // 新添加的播放列表ID
    'PLQWMqkNuwweLu5hFpWCVHiRkdHhrHBpop'    // 新添加的播放列表ID
];
$maxResults = 20;
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
