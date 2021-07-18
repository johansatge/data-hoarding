<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();
$output = [];

if (!empty($args['help']) || count($args['_']) === 0)
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Compress videos to H264/AAC (will save to file1.mp4 and keep original in file1.orig.mp4)',
    str_repeat('-', 30),
    'Usage:',
    '$ compress_video file1.mp4 file2.mp4 [--options]',
    str_repeat('-', 30),
    'Options:',
    '--force-720p       Force output to 1280x720',
    '--fps=[number]     Force FPS (default is to stick to source)',
    '--quality=[number] Set x264 RF value (default is 25)',
    '--no-audio Remove audio track',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

foreach($args['_'] as $path)
{
  $dest_path = preg_replace('#\.([^.]+)$#i', '.out.mp4', $path);
  $orig_path = preg_replace('#\.([^.]+)$#i', '.orig.$1', $path);
  $params = [
    '--input'        => '"' . $path . '"',
    '--output'       => '"' . $dest_path . '"',
    '--format'       => 'av_mp4',
    '--encoder'      => 'x264',
    '--encoder-preset'  => 'slow', // ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow, placebo
    '--encoder-profile' => 'high', // auto, main, high
    '--encoder-tune'    => 'film', // none, film, animation, grain, stillimage, psnr, ssim, fastdecode, zerolatency
    '--quality'      => !empty($args['quality']) ? intval($args['quality']) : 25,
    '--aencoder'     => 'ca_aac',
    '--ab'           => '112',
  ];
  if (!empty($args['fps']))
  {
    $params['--rate'] = intval($args['fps']);
  }
  if (!empty($args['force-720p']))
  {
    $params['--width'] = 1280;
    $params['--height'] = 720;
  }
  if (!empty($args['no-audio']))
  {
    $params['--audio'] = 'none';
  }
  $command = '/Applications/HandbrakeCLI';
  foreach($params as $param => $value)
  {
    $command .= ' ' . $param . ' ' . $value;
  }
  echo str_repeat('-', 20) . "\n";
  echo 'Running ' . $command . "\n";
  echo str_repeat('-', 20) . "\n";
  $start_time = time();
  $stream = popen($command . ' 2>&1', 'r');
  while (!feof($stream))
  {
    echo fread($stream, 4096);
    flush();
  }
  pclose($stream);
  $output[] = 'Video:             ' . $path;
  $output[] = 'Elapsed time:      ' . (time() - $start_time) . 's';
  $output[] = 'Original filesize: ' . round(filesize($path) / 1000 / 1000, 2) . 'M';
  $output[] = 'New filesize:      ' . round(filesize($dest_path) / 1000 / 1000, 2) . 'M';
  $output[] = str_repeat('-', 20);
  rename($path, $orig_path);
  rename($dest_path, preg_replace('#\.out\.mp4$#', '.mp4', $dest_path));
}
foreach($output as $line)
{
  echo $line . "\n";
}
