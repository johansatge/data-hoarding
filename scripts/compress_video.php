<?php

/**
 * Transcode video files with ffmpeg
 * - Video track with the right encoder and quality
 * - Audio track with AAC & fixed quality (or no audio track)
 * - GoPro metadata track if available
 *
 * Resources:
 * - https://coderunner.io/how-to-compress-gopro-movies-and-keep-metadata/
 * - https://github.com/gopro/gpmf-parser
 * - https://github.com/stilldavid/gopro-utils#extracting-the-metadata-file
 */

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();
$output = [];
$totalOriginal = 0;
$totalDest = 0;

if (!empty($args['help']) || count($args['_']) === 0)
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Compress videos to HEVC/AAC (will save to file1.mp4 and keep original in file1.orig.mp4)',
    str_repeat('-', 30),
    'Usage:',
    '$ compress_video file1.mp4 file2.mp4 [--options]',
    str_repeat('-', 30),
    'Options:',
    '--h264              Re-encode the video with libx264 (instead of hevc_videotoolbox)',
    '--x265              Re-encode the video with libx265 (instead of hevc_videotoolbox) (very slow)',
    '--force-1080p       Re-encode in 1080p',
    '--fps=[number]      Force FPS (default is to stick to source)',
    '--quality=[number]  Encoding quality (CRF with x264, Constant Quality with HEVC) (defaults: 25, 60)',
    '                    If 60 doesn\'t compress enough (a snowy GoPro video for instance) try 50',
    '--speed=[number]    Speed up the video (e.g., x2, x4, x8) (will also remove audio track)',
    '--no-audio          Remove audio track',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$codec = isset($args['h264']) ? 'h264' : (isset($args['x265']) ? 'x265' : 'hevc_videotoolbox');
$speed = !empty($args['speed']) ? floatval(preg_replace('/[^0-9.]/', '', $args['speed'])) : 1;

foreach($args['_'] as $path)
{
  $output[] = 'Video:             ' . $path;
  unset($ffProbeStout);
  exec('ffprobe -print_format json -show_format -show_streams "' . $path . '" 2>/dev/null', $ffProbeStout);
  $ffProbeData = json_decode(implode('', $ffProbeStout), true);
  if (!is_array($ffProbeData) || json_last_error() !== JSON_ERROR_NONE)
  {
    $output[] = 'Error: Could not parse video metadata (invalid JSON)';
    continue;
  }
  if (empty($ffProbeData['streams']))
  {
    $output[] = 'Error: No streams found';
    continue;
  }
  if (empty($ffProbeData['format']['duration']) || floatval($ffProbeData['format']['duration']) <= 0)
  {
    $output[] = 'Error: Invalid or missing video duration';
    continue;
  }

  $origPath = preg_replace('#\.([^.]+)$#i', '.orig.$1', $path);
  $destPath = preg_replace('#\.([^.]+)$#i', ($speed > 1 ? ' x' . $speed : '') . '.out.mp4', $path);
  
  if (file_exists($destPath))
  {
    $output[] = 'Destination file already exists';
    continue;
  }

  // Build ffmpeg command
  $params = [
    '-i "' . $path . '"',
    // Copy global metadata
    // https://coderunner.io/how-to-compress-gopro-movies-and-keep-metadata/
    '-map_metadata 0',
  ];

  // Codec params & quality
  if ($codec === 'h264')
  {
    $params[] = '-c:v libx264';
    $params[] = '-preset slow';
    $params[] = '-crf ' . (!empty($args['quality']) ? intval($args['quality']) : 25);
    // '-tune film', // x264 tune (film doesn't exist with hevc?)
  }
  if ($codec === 'hevc_videotoolbox')
  {
    $params[] = '-c:v hevc_videotoolbox';
    $params[] = '-q:v ' . (!empty($args['quality']) ? intval($args['quality']) : 60); // https://stackoverflow.com/a/69668183
    $params[] = '-tag:v hvc1'; // Needed so macOS recognizes the media as HEVC (https://discussions.apple.com/thread/253196462)
  }
  // Extremely slow on mac m1 (1fps for 4k source)
  // Going with a preset fast or medium is better (6-7fps for 4k source) but ends up with a worse result than videotoolbox
  // CRF 30 is similar to q:v 45 with videotoolbox but with a worse or similar visual quality
  if ($codec === 'x265')
  {
    $params[] = '-c:v libx265';
    $params[] = '-preset slow';
    $params[] = '-crf ' . (!empty($args['quality']) ? intval($args['quality']) : 23);
    $params[] = '-tag:v hvc1'; // Needed so macOS recognizes the media as HEVC (https://discussions.apple.com/thread/253196462)
  }
  
  // Video filters
  $videoFilters = [];
  if ($speed > 1)
  {
    $videoFilters[] = 'setpts=' . (1 / $speed) . '*PTS';
  }
  if (!empty($args['force-1080p']))
  {
    $videoFilters[] = 'scale=-1:1080';
  }
  if (!empty($args['fps']))
  {
    $videoFilters[] = 'fps=' . intval($args['fps']);
  }
  if (!empty($videoFilters))
  {
    $params[] = '-filter:v "' . implode(',', $videoFilters) . '"';
  }

  // Audio track
  if (empty($args['no-audio']) && $speed === 1)
  {
    $params[] = '-map 0:a:0?'; // "?" -> only map if audio stream exists
    $params[] = '-c:a aac';
    $params[] = '-b:a 192k';
  }

  // Video track
  $params[] = '-map 0:v:0';

  // GoPro GPMF track if available
  foreach($ffProbeData['streams'] as $streamIndex => $stream)
  {
    $codecType = !empty($stream['codec_type']) ? $stream['codec_type'] : '';
    $codecTag = !empty($stream['codec_tag_string']) ? $stream['codec_tag_string'] : '';
    if ($codecType === 'data' && $codecTag === 'gpmd')
    {
      $params[] = '-map 0:' . $streamIndex;
      $params[] = '-c:d copy';
      break;
    }
  }

  // Final command
  $command = 'ffmpeg';
  foreach($params as $param)
  {
    $command .= " \\\n" . $param;
  }
  $command .= " \\\n" . '"' . $destPath . '"';
  echo str_repeat('-', 20) . "\n";
  echo 'Running ' . $command . "\n";
  echo str_repeat('-', 20) . "\n";
  $start_time = time();
  $stream = popen($command . ' 2>&1', 'r');
  $ffmpegFullOutput = '';
  while (!feof($stream))
  {
    $ffmpegStdout = fread($stream, 4096);
    $ffmpegFullOutput .= $ffmpegStdout;
    flush();
    preg_match('#fps=([ 0-9]+).*time=([0-9]+):([0-9]+):([0-9]+).([0-9]+)#', $ffmpegStdout, $ffmpegData);
    if (!empty($ffmpegData[0]))
    {
      $fps = floatval(trim($ffmpegData[1]));
      $encodedDuration = $ffmpegData[2] * 60 * 60 + $ffmpegData[3] * 60 + $ffmpegData[4];
      $totalDuration = intval($ffProbeData['format']['duration']);
      if ($totalDuration > 0)
      {
        $percent = intval($encodedDuration / $totalDuration * 100);
        echo $encodedDuration . 's transcoded on ' . $totalDuration . 's (' . $percent . '% at ' . $fps . 'fps)' . "\r";
      }
    }
  }
  echo "\n";
  $code = pclose($stream);
  if ($code !== 0)
  {
    $output[] = 'Error: ffmpeg exited with code ' . $code;
    $output[] = $ffmpegFullOutput;
    continue;
  }
  if (!is_readable($destPath))
  {
    $output[] = 'Error: Output file was not created';
    continue;
  }
  $originalFilesize = round(filesize($path) / 1000 / 1000, 2);
  $destFilesize = round(filesize($destPath) / 1000 / 1000, 2);
  $compressionRatio = ($originalFilesize - $destFilesize) / $originalFilesize * 100;
  $isWorthwile = $compressionRatio >= 10; // at least 10% smaller
  $output[] = 'Elapsed time:      ' . (time() - $start_time) . 's';
  $output[] = 'Original filesize: ' . $originalFilesize . 'M';
  $output[] = 'New filesize:      ' . $destFilesize . 'M';
  $output[] = 'Compression ratio: ' . round($compressionRatio, 1) . '% ' . ($isWorthwile ? '✅' : '⚠️');

  // Revert if compressed file is bigger or compression is less than 5%
  if (!$isWorthwile)
  {
    unlink($destPath);
    $output[] = str_repeat('-', 20);
    continue;
  }

  $output[] = str_repeat('-', 20);
  rename($path, $origPath);
  rename($destPath, preg_replace('#\.out\.mp4$#', '.mp4', $destPath));
  $totalOriginal += $originalFilesize;
  $totalDest += $destFilesize;
}
foreach($output as $line)
{
  echo $line . "\n";
}
echo 'Before: ' . $totalOriginal . 'M' . "\n";
echo 'After: ' . $totalDest . 'M' . "\n";
