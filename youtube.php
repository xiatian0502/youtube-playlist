<?php

$playlistIds = [
    // 添加你的播放列表 ID
    'PLMUs_BF93V5ZSwXgrcGre0aGngSyOfv7x', 
    'PLwACruPGUorXHJa2kX5Olh-YbKAb3rm-q', 
    'PL0VLshn35eZIQ8eIvFcj0TiuiiVl08Jvf', 
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
    'PLXDl0OrlJGsh7UhLSyKQdL1RZcMMbaJxL', 
    // 添加更多播放列表 ID，根据需要添加
];

$maxResults = 20;
$cacheTimeInMinutes = 1440; // 缓存时间设置为1天（1440分钟）
$cacheDir = __DIR__ . '/cache'; // 缓存目录

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true); // 创建缓存目录
}

// 在GitHub Actions下，我们没有服务器路径，因此使用临时文件路径进行日志记录
$logFile = $cacheDir . '/yt_dl_log.txt'; // 指定日志文件路径

// 清空日志文件
file_put_contents($logFile, "");

// 检查 yt-dlp 版本
$ytDlpVersion = shell_exec("yt-dlp --version");
file_put_contents($logFile, "yt-dlp version: $ytDlpVersion\n", FILE_APPEND);

header('Content-Type: application/x-mpegURL');
header('Content-Disposition: attachment; filename="playlist.m3u"');
echo "#EXTM3U\n";

foreach ($playlistIds as $playlistId) {
    $playlistUrl = "https://www.youtube.com/playlist?list=$playlistId";
    $cacheFile = $cacheDir . '/' . sha1($playlistId) . '.json';

    // 如果缓存文件有效且存在，直接读取缓存
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < ($cacheTimeInMinutes * 60)) {
        $output = file_get_contents($cacheFile);
    } else {
        // 获取播放列表信息并缓存
        $command = "yt-dlp -J --flat-playlist --playlist-end $maxResults $playlistUrl 2>> $logFile";
        $output = shell_exec($command);
        if ($output) {
            file_put_contents($cacheFile, $output);
        } else {
            file_put_contents($logFile, "Failed to fetch playlist data for $playlistUrl\n", FILE_APPEND);
            continue;
        }
    }

    $data = json_decode($output, true);

    if (is_array($data) && isset($data['entries'])) {
        foreach ($data['entries'] as $entry) {
            $videoId = $entry['id'];
            $videoUrl = 'https://www.youtube.com/watch?v=' . $videoId;
            $videoCacheFile = $cacheDir . '/' . sha1($videoId) . '.json';

            // 如果视频缓存文件有效且存在，直接读取缓存
            if (file_exists($videoCacheFile) && (time() - filemtime($videoCacheFile)) < ($cacheTimeInMinutes * 60)) {
                $streamUrl = trim(file_get_contents($videoCacheFile));
            } else {
                // 优先获取音频+视频流 URL
                $command = "yt-dlp -f 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/mp4' --get-url $videoUrl 2>> $logFile";
                $streamUrl = shell_exec($command);

                // 记录调试信息
                file_put_contents($logFile, "Command: $command \nResult: $streamUrl\n", FILE_APPEND);

                if (!$streamUrl) {
                    // 尝试获取最高可用格式的视频流 URL
                    $command = "yt-dlp -f 'best' --get-url $videoUrl 2>> $logFile";
                    $streamUrl = trim(shell_exec($command));

                    // 记录调试信息
                    file_put_contents($logFile, "Fallback Command: $command \nFallback Result: $streamUrl\n", FILE_APPEND);
                } else {
                    $streamUrl = trim($streamUrl);
                }

                if ($streamUrl) {
                    file_put_contents($videoCacheFile, $streamUrl);
                } else {
                    file_put_contents($logFile, "Failed to retrieve stream URL for $videoUrl\n", FILE_APPEND);
                    continue;
                }
            }

            if ($streamUrl) {
                $videoData = shell_exec("yt-dlp -J $videoUrl 2>> $logFile");
                $videoData = json_decode($videoData, true);
                if (isset($videoData['title']) && isset($videoData['uploader'])) {
                    $groupTitle = htmlspecialchars($videoData['uploader'], ENT_QUOTES, 'UTF-8');
                    $videoTitle = htmlspecialchars($videoData['title'], ENT_QUOTES, 'UTF-8');

                    echo "#EXTINF:-1 group-title=\"$groupTitle\",$videoTitle\n";
                    echo "$streamUrl\n";
                }
            } else {
                echo "#EXTINF:-1,Failed to retrieve stream URL for $videoUrl\n";
                echo "https://www.example.com/empty.m3u8\n";
            }
        }
    } else {
        echo "#EXTINF:-1,No videos found for playlist $playlistUrl\n";
        echo "https://www.example.com/empty.m3u8\n";
    }
}
?>
