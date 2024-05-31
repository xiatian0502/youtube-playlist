<?php
$playlistIds = [
    'PLMUs_BF93V5ZSwXgrcGre0aGngSyOfv7x', // 你的第一个播放列表 ID
    'PLwACruPGUorXHJa2kX5Olh-YbKAb3rm-q', // 你的第二个播放列表 ID
    // 添加更多播放列表ID
];

$maxResults = 20;
$cacheTimeInMinutes = 1440; // 缓存时间设置为1天（1440分钟）
$cacheDir = DIR . '/cache'; // 缓存目录

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true); // 创建缓存目录
}

$logFile = '/var/www/html/yt_dl_log.txt'; // 指定一个日志文件

// 清空日志文件
file_put_contents($logFile, "");

header('Content-Type: application/x-mpegURL');
header('Content-Disposition: attachment; filename="playlist.m3u"');
echo "#EXTM3U\n";

foreach ($playlistIds as $index => $playlistId) {
    $playlistUrl = "https://www.youtube.com/playlist?list=$playlistId";
    $cacheFile = $cacheDir . '/' . sha1($playlistId) . '.json';

    // 如果缓存文件有效且存在，直接读取缓存
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < ($cacheTimeInMinutes * 60)) {
        $output = file_get_contents($cacheFile);
    } else {
        // 获取播放列表信息并缓存
        $command = "/usr/local/bin/yt-dlp -J --flat-playlist --playlist-end $maxResults $playlistUrl 2>> $logFile";
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
                // 优先获取1920x1080P的视频流 URL，如果不可用则获取最高可用格式
                $streamUrl = trim(shell_exec("/usr/local/bin/yt-dlp -f 'bestvideo[width=1920][height=1080]+bestaudio/best' --get-url $videoUrl 2>> $logFile"));

                if (!$streamUrl) {
                    // 尝试获取最高可用格式的视频流 URL
                    $streamUrl = trim(shell_exec("/usr/local/bin/yt-dlp -f 'best' --get-url $videoUrl 2>> $logFile"));
                }

                if ($streamUrl) {
                    file_put_contents($videoCacheFile, $streamUrl);
                } else {
                    file_put_contents($logFile, "Failed to retrieve stream URL for $videoUrl\n", FILE_APPEND);
                    continue;
                }
            }

            if ($streamUrl) {
                $videoData = json_decode(shell_exec("/usr/local/bin/yt-dlp -J $videoUrl 2>> $logFile"), true);
                if (isset($videoData['title']) && isset($videoData['uploader'])) {
                    $groupTitle = htmlspecialchars($videoData['uploader'], ENT_QUOTES, 'UTF-8');
                    $videoTitle = htmlspecialchars($videoData['title'], ENT_QUOTES, 'UTF-8');

                    echo "#EXTINF:-1 group-title=\"$groupTitle\",$videoTitle\n";
                    echo "$streamUrl\n";
                }
            } else {
                echo "#EXTINF:-1,Failed to retrieve stream URL for $videoUrl\n";
                echo "https://www.example.com/empty\n";
            }
        }
    } else {
        echo "#EXTINF:-1,No videos found for playlist $playlistUrl\n";
        echo "https://www.example.com/empty\n";
    }
}
?>