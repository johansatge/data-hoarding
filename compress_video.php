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
$total_original = 0;
$total_dest = 0;

if (!empty($args['help']) || count($args['_']) === 0 || (empty($args['h264']) && empty($args['hevc'])))
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Compress videos to H264/HEVC/AAC (will save to file1.mp4 and keep original in file1.orig.mp4)',
    str_repeat('-', 30),
    'Usage:',
    '$ compress_video --h264 file1.mp4 file2.mp4 [--options]',
    str_repeat('-', 30),
    'Options:',
    '--h264          Re-encode the video with libx264',
    '--hevc          Re-encode the video with libx265',
    '--fps=[number]  Force FPS (default is to stick to source)',
    '--crf=[number]  Set x264/hevc Constant Rate Factor value (default is 25/28)',
    '--no-audio      Remove audio track',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$codec = $args['h264'] ? 'libx264' : 'libx265';
$defaultCrf = $codec === 'libx264' ? 25 : 28;

foreach($args['_'] as $path)
{
  $output[] = 'Video:             ' . $path;
  exec('ffprobe -print_format json -show_format -show_streams "' . $path . '" 2>/dev/null', $ffProbeStout);
  $ffProbeData = json_decode(implode('', $ffProbeStout), true);
  if (empty($ffProbeData['streams']))
  {
    $output[] = 'No streams found';
    continue;
  }
  $dest_path = preg_replace('#\.([^.]+)$#i', '.out.mp4', $path);
  $orig_path = preg_replace('#\.([^.]+)$#i', '.orig.$1', $path);
  $params = [
    '-i "' . $path . '"',
    // '-map 0', // Copy all streams
    // '-copy_unknown', // Copy unrecognized streams (like GoPro data)
    '-map_metadata 0', // Copy global metadata
    // '-c copy', // Codec: copy all streams as is
    '-c:v ' . $codec, // Video codec: use the h264/hevc depending on input
    '-preset ' . ($codec === 'libx264' ? 'slow' : 'faster'), // x264/hevc preset
    // '-tune film', // x264/hevc tune (film doesn't exist with hevc?)
    '-crf ' . (!empty($args['crf']) ? intval($args['crf']) : $defaultCrf),
  ];
  if ($codec === 'libx265')
  {
    // Needed so macOS recognizes the media as HEVC
    // https://discussions.apple.com/thread/253196462
    $params[] = '-tag:v hvc1';
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
    $encoder = !empty($stream['tags']['encoder']) ? $stream['tags']['encoder'] : '';
    if ($codecType === 'video' || $codecType === 'audio')
    {
      $shortCodecType = substr($codecType, 0, 1);
      $params[] = '-map 0:' . $shortCodecType; // Map video/audio from input file 0
      if (!empty($handlerName))
      {
        // Save original handler
        $params[] = '-metadata:s:' . $shortCodecType . ': handler="' . $handlerName . '"';
      }
    }
    // @todo write track or export it to .bin:
    // https://github.com/stilldavid/gopro-utils#extracting-the-metadata-file
    // if ($codecType === 'data' && !empty($handlerName) && strpos($handlerName, 'GoPro MET') !== false)
    // {
    //   // Map data from input file 0 by handle name
    //   $params[] = '-map 0:m:handler_name:"' . $handlerName . '"';
    //   // Set same handler name in corresponding destination stream
    //   $params[] = '-metadata:s:d:' . $dataStreamId . ' handler="' . $handlerName . '"';
    //   // Flag stream as "gpmd" wich ffmpeg knows,
    //   // oterwhise it won't copy the stream even with "-copy_unknown"
    //   $params[] = '-tag:d:' . $dataStreamId . ' "gpmd"';
    //   $dataStreamId += 1;
    // }
  }
  $command = 'ffmpeg';
  foreach($params as $param)
  {
    $command .= " \\\n" . $param;
  }
  $command .= ' "' . $dest_path . '"';
  echo str_repeat('-', 20) . "\n";
  echo 'Running ' . $command . "\n";
  echo str_repeat('-', 20) . "\n";
  $start_time = time();
  $stream = popen($command . ' 2>&1', 'r');
  while (!feof($stream))
  {
    // @todo better ffmpeg output
    // frame=  197 fps=1.6 q=38.3 size=    6144kB time=00:00:03.39 bitrate=14838.5kbits/s speed=0.0283x
    echo fread($stream, 4096);
    flush();
  }
  $code = pclose($stream);
  if ($code !== 0)
  {
    continue;
  }
  $original_filesize = round(filesize($path) / 1000 / 1000, 2);
  $dest_filesize = round(filesize($dest_path) / 1000 / 1000, 2);
  $output[] = 'Elapsed time:      ' . (time() - $start_time) . 's';
  $output[] = 'Original filesize: ' . $original_filesize . 'M';
  $output[] = 'New filesize:      ' . $dest_filesize . 'M';
  $output[] = str_repeat('-', 20);
  rename($path, $orig_path);
  rename($dest_path, preg_replace('#\.out\.mp4$#', '.mp4', $dest_path));
  $total_original += $original_filesize;
  $total_dest += $dest_filesize;
}
foreach($output as $line)
{
  echo $line . "\n";
}
echo str_repeat('-', 20) . "\n";
echo 'Before: ' . $total_original . 'M' . "\n";
echo 'After: ' . $total_dest . 'M' . "\n";
