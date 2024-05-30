<?php

$playlistIds = ['PLQWMqkNuwweK2NUFEex3Jked5lBWcUIJc&index=6', 'PLNXJ_YC1PDA1L6H_ec0pn25QkDdK_8KrB', 'PLrVKx-I9kvCSllIRl4wpzLpJtUPUOSPb8']; // 在此处定义播放列表ID
$maxResults = 20;
$API_key = 'AIzaSyAONZd3f8TN6QZS39WCeddl7YqP1TdhkkQ'; // 你的API_KEY

$categories = []; // 自动生成的分类将放在此数组中

function categorizeVideo($title) {
    if (strpos($title, "【最HOT 5000秒】") !== false) {
        return "最HOT 5000秒";
    } else if (strpos($title, "【每日必看】") !== false) {
        return "每日必看";
    } 
    // 继续添加更多的分类条件
    return "其他";  // 默认为 '其他' 分类
}

foreach ($playlistIds as $playlistId) {
    // 使用播放列表ID获取视频列表
    $videoList = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $maxResults . '&playlistId=' . $playlistId . '&key=' . $API_key), true);

    if (!$videoList || !isset($videoList['items'])) {
        $categories["未分类"][] = "#播放列表 " . $playlistId . " 未找到";
        continue;
    }

    foreach ($videoList['items'] as $item) {
        $youtubeUrl = 'https://www.youtube.com/watch?v=' . $item['snippet']['resourceId']['videoId'];
        $videoTitle = $item['snippet']['title'];

        // 利用 yt-dlp 获取流媒体链接
        $command = "/home/runner/.local/bin/yt-dlp -f best --get-url --no-playlist --no-warnings --force-generic-extractor --user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0' --youtube-skip-dash-manifest " . escapeshellarg($youtubeUrl);
        $streamUrl = shell_exec($command);

        // 处理可能的空链接
        if ($streamUrl !== null) {
            $streamUrl = trim($streamUrl);
        } else {
            $streamUrl = $youtubeUrl;  // 如果获取流媒体链接失败，就用 YouTube 视频链接代替
        }

        // 分类视频
        $category = categorizeVideo($videoTitle);
        if (!isset($categories[$category])) {
            $categories[$category] = [];  // 如果分类数组内不存在此分类，创建新的分类条目
        }
        $categories[$category][] = "#EXTINF:-1," . $videoTitle . PHP_EOL . $streamUrl . PHP_EOL;
    }
}

// 打开文件
$file = fopen('playlist.m3u', 'w');
fwrite($file, "#EXTM3U" . PHP_EOL);

// 写入分类内容
foreach ($categories as $category => $videos) {
    if (!empty($videos)) {
        fwrite($file, "#EXT-X-GROUP-ID:" . $category . PHP_EOL); // 写入分类标签
        foreach ($videos as $video) {
            fwrite($file, $video);
        }
    }
}

// 关闭文件
fclose($file);

header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');
