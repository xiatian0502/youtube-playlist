<?php

$playlistIds = [
    'PLQWMqkNuwweK2NUFEex3Jked5lBWcUIJc&index=6',
    'PLNXJ_YC1PDA1L6H_ec0pn25QkDdK_8KrB',
    'PLrVKx-I9kvCSllIRl4wpzLpJtUPUOSPb8',  // 原有播放列表ID
    'PLwACruPGUorXHJa2kX5Olh-YbKAb3rm-q',   // 新添加的播放列表ID
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
