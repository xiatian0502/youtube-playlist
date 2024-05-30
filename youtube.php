<?php

$playlistIds = ['PLQWMqkNuwweK2NUFEex3Jked5lBWcUIJc&index=6', 'PLNXJ_YC1PDA1L6H_ec0pn25QkDdK_8KrB']; // 直接在脚本中定义播放列表ID
$maxResults = 20;

$API_key = 'AIzaSyAONZd3f8TN6QZS39WCeddl7YqP1TdhkkQ'; // 你的API_KEY

// 打开文件
$file = fopen('playlist.m3u', 'w');
fwrite($file, "#EXTM3U" . PHP_EOL);

foreach ($playlistIds as $playlistId) {
    // 使用播放列表ID获取视频列表
    $videoList = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $maxResults . '&playlistId=' . $playlistId . '&key=' . $API_key . ''), true);

    if (!$videoList || !isset($videoList['items'])) {
        fwrite($file, "#播放列表" . $playlistId . "未找到" . PHP_EOL);
        continue;
    }

    foreach ($videoList['items'] as $item) {
        $youtubeUrl = 'https://www.youtube.com/watch?v=' . $item['snippet']['resourceId']['videoId'];
        
        // 使用 yt-dlp 获取流媒体链接并返回格式为 m3u8
        $command = "/home/runner/.local/bin/yt-dlp -f best --get-url --no-playlist --no-warnings --force-generic-extractor --user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0' --youtube-skip-dash-manifest " . escapeshellarg($youtubeUrl);
        $streamUrl = shell_exec($command);

        // 检查 $streamUrl 是否为 null，并移除可能的额外新行
        if ($streamUrl !== null) {
            $streamUrl = trim($streamUrl);
        } else {
            $streamUrl = $youtubeUrl;  // 如果获取流媒体链接失败，就使用 YouTube 视频链接
        }
        
        fwrite($file, "#EXTINF:-1," . $item['snippet']['title'] . PHP_EOL);
        fwrite($file, $streamUrl . PHP_EOL);
    }
}

// 关闭文件
fclose($file);

header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');
