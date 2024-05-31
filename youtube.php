<?php
$playlistIds = [
    'PLMUs_BF93V5ZSwXgrcGre0aGngSyOfv7x',
    'PLwACruPGUorXHJa2kX5Olh-YbKAb3rm-q',
    // 添加更多播放列表 ID
];

$maxResults = 20;
$cacheTimeInMinutes = 1440; // 缓存时间设置为1天（1440分钟）
$cacheDir = __DIR__ . '/cache'; // 缓存目录

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true); // 创建缓存目录
}

$logFile = __DIR__ . '/yt_dl_log.txt'; // 修改日志文件路径为相对路径
file_put_contents($logFile, ""); // 清空日志文件

// 检查 yt-dlp 版本
$ytDlpPath = shell_exec("which yt-dlp");
if ($ytDlpPath) {
    $ytDlpVersion = shell_exec("yt-dlp --version");
    file_put_contents($logFile, "yt-dlp version: $ytDlpVersion\n", FILE_APPEND);
} else {
    file_put_contents($logFile, "yt-dlp not found\n", FILE_APPEND);
    exit(1);
}

$m3uFileContent = "#EXTM3U\n";

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
                // 优先获取1920x1080P的视频流 URL，如果不可用则获取最高可用格式
                $command = "yt-dlp -f 'bestvideo[height=1080]+bestaudio/best' --get-url $videoUrl 2>> $logFile";
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

                    $m3uFileContent .= "#EXTINF:-1 group-title=\"$groupTitle\",$videoTitle\n";
                    $m3uFileContent .= "$streamUrl\n";
                }
            } else {
                $m3uFileContent .= "#EXTINF:-1,Failed to retrieve stream URL for $videoUrl\n";
                $m3uFileContent .= "https://www.example.com/empty.m3u8\n";
            }
        }
    } else {
        $m3uFileContent .= "#EXTINF:-1,No videos found for playlist $playlistUrl\n";
        $m3uFileContent .= "https://www.example.com/empty.m3u8\n";
    }
}

$m3uFilePath = __DIR__ . '/ytdianbo.m3u';
file_put_contents($m3uFilePath, $m3uFileContent);
file_put_contents($logFile, "M3U file has been created successfully: $m3uFilePath\n", FILE_APPEND);
