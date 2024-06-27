<?php

/**
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

if (!empty($args['help']) || count($args['_']) === 0 || (empty($args['h264']) && empty($args['hevc'])))
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Compress videos to H264/HEVC/AAC (will save to file1.mp4, extract EXIF to file1.json and keep original in file1.orig.mp4)',
    str_repeat('-', 30),
    'Usage:',
    '$ compress_video --h264 file1.mp4 file2.mp4 [--options]',
    str_repeat('-', 30),
    'Options:',
    '--h264              Re-encode the video with libx264',
    '--hevc              Re-encode the video with libx265',
    '--force-1080p       Re-encode in 1080p',
    '--fps=[number]      Force FPS (default is to stick to source)',
    '--quality=[number]  Encoding quality (CRF with x264, Constant Quality with HEVC) (defaults: 25, 45)',
    '--no-audio          Remove audio track',
    '--no-metadata        Don\'t export video metadata',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$codec = isset($args['h264']) ? 'h264' : 'hevc';
$withMetadata = !isset($args['no-metadata']);

foreach($args['_'] as $path)
{
  $output[] = 'Video:             ' . $path;
  unset($ffProbeStout);
  exec('ffprobe -print_format json -show_format -show_streams "' . $path . '" 2>/dev/null', $ffProbeStout);
  $ffProbeData = json_decode(implode('', $ffProbeStout), true);
  if (empty($ffProbeData['streams']))
  {
    $output[] = 'No streams found';
    continue;
  }

  $origPath = preg_replace('#\.([^.]+)$#i', '.orig.$1', $path);
  $destPath = preg_replace('#\.([^.]+)$#i', '.out.mp4', $path);
  if (file_exists($destPath))
  {
    $output[] = 'Destination file already exists';
    continue;
  }

  // Extract metadata tracks with ffprobe (like GoPro GPS & accelerometer tracks)
  // -ee to parse all metadata/streams
  // -g3 to keep hierarchy in streams
  unset($exiftoolStdout);
  if ($withMetadata)
  {
    exec('exiftool -ee -g3 -b -json "' . $path . '"', $exiftoolStdout);
    $exiftoolData = json_decode(implode('', $exiftoolStdout), true);
    if (empty($exiftoolData[0]['SourceFile']))
    {
      $output[] = 'Could not read file metadata';
      continue;
    }
    $dataPath = preg_replace('#\.([^.]+)$#i', '.json', $path);
    if (is_readable($dataPath))
    {
      $output[] = 'Metadata file already exists';
      continue;
    }
    file_put_contents($dataPath, json_encode($exiftoolData[0], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  }

  // Transcode file with ffmpeg
  $params = [
    '-i "' . $path . '"',
    '-map_metadata 0', // Copy global metadata
  ];
  if ($codec === 'h264')
  {
    $params[] = '-c:v libx264';
    $params[] = '-preset slow';
    $params[] = '-crf ' . (!empty($args['quality']) ? intval($args['quality']) : 25);
    // '-tune film', // x264 tune (film doesn't exist with hevc?)
  }
  if ($codec === 'hevc')
  {
    $params[] = '-c:v hevc_videotoolbox'; // libx265 is too slow, even on M1
    $params[] = '-q:v ' . (!empty($args['quality']) ? intval($args['quality']) : 45); // https://stackoverflow.com/a/69668183
    $params[] = '-tag:v hvc1'; // Needed so macOS recognizes the media as HEVC (https://discussions.apple.com/thread/253196462)
  }
  if (!empty($args['force-1080p']))
  {
    $params[] = '-filter:v scale=-1:1080';
  }
  if (!empty($args['fps']))
  {
    $params[] = '-filter:v fps=' . intval($args['fps']);
  }
  if (!empty($args['no-audio']))
  {
    $params[] = '-an'; // Drop all audio streams
  }
  else
  {
    $params[] = '-c:a aac';
    $params[] = '-b:a 192k';
  }
  $dataStreamId = 0;
  foreach($ffProbeData['streams'] as $stream)
  {
    $codecType = !empty($stream['codec_type']) ? $stream['codec_type'] : '';
    $handlerName = !empty($stream['tags']['handler_name']) ? $stream['tags']['handler_name'] : '';
    // Map video/audio from input file 0 & save original handler if possible
    if ($codecType === 'video' || $codecType === 'audio')
    {
      $shortCodecType = substr($codecType, 0, 1);
      $params[] = '-map 0:' . $shortCodecType;
      if (!empty($handlerName))
      {
        $params[] = '-metadata:s:' . $shortCodecType . ': handler="' . trim($handlerName) . '"';
      }
    }
  }
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
  while (!feof($stream))
  {
    $ffmpegStdout = fread($stream, 4096);
    flush();
    preg_match('#fps=([ 0-9]+).*time=([0-9]+):([0-9]+):([0-9]+).([0-9]+)#', $ffmpegStdout, $ffmpegData);
    if (!empty($ffmpegData[0]))
    {
      $fps = floatval(trim($ffmpegData[1]));
      $hours = $ffmpegData[2];
      $minutes = $ffmpegData[3];
      $seconds = $ffmpegData[4];
      $encodedDuration = $hours * 60 * 60 + $minutes * 60 + $seconds;
      $totalDuration = intval($ffProbeData['format']['duration']);
      echo implode(' ', [
        $encodedDuration . 's',
        'transcoded on',
        $totalDuration . 's',
        '(' . intval($encodedDuration / $totalDuration * 100) . '% at ' . $fps . 'fps)',
      ]) . "\r";
    }
  }
  echo "\n";
  $code = pclose($stream);
  if ($code !== 0)
  {
    continue;
  }
  $originalFilesize = round(filesize($path) / 1000 / 1000, 2);
  $destFilesize = round(filesize($destPath) / 1000 / 1000, 2);
  $output[] = 'Elapsed time:      ' . (time() - $start_time) . 's';
  $output[] = 'Original filesize: ' . $originalFilesize . 'M';
  $output[] = 'New filesize:      ' . $destFilesize . 'M';
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
