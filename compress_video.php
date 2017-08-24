<?php

require('parse_argv.php');
$args = parse_argv();
$output = [];

if (count($args['_']) === 0)
{
  echo 'At least one source file needed' . "\n";
  exit(1);
}

foreach($args['_'] as $path)
{
  $dest_path = preg_replace('#\.(mp4|mov)$#i', '.out.$1', $path);
  $params = [
    '--input'        => '"' . $path . '"',
    '--output'       => '"' . $dest_path . '"',
    '--format'       => 'av_mp4',
    '--encoder'      => 'x264',
    '--x264-preset'  => 'slow', // ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow, placebo
    '--x264-profile' => 'high', // baseline, main, high, high10, high422, high444
    '--x264-tune'    => 'film', // film, animation, grain, stillimage, psnr, ssim, fastdecode, zerolatency
    '--quality'      => !empty($args['quality']) ? intval($args['quality']) : 25,
    //'--encopts', 'vbv-maxrate=3000:vbv-bufsize=3000',
    '--audio'        => '1',
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
  $command = '/Applications/HandbrakeCLI';
  foreach($params as $param => $value)
  {
    $command .= ' ' . $param . ' ' . $value;
  }
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
}
foreach($output as $line)
{
  echo $line . "\n";
}