<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();

if (!empty($args['help']) || count($args['_']) !== 3)
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Compare two videos by extracting frames in the specified directory',
    str_repeat('-', 30),
    'Usage:',
    '$ compare_video file1.mp4 file2.mp4 destination/directory',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$video1 = $args['_'][0];
$video2 = $args['_'][1];
$destPath = $args['_'][2];
$destVideo1 = rtrim($destPath, '/') . '/%04d_1.bmp';
$destVideo2 = rtrim($destPath, '/') . '/%04d_2.bmp';

@mkdir($destPath, 0755, true);

runCommand(sprintf('ffmpeg -i "%s" -vf fps=0.2 "%s"', $video1, $destVideo1));
runCommand(sprintf('ffmpeg -i "%s" -vf fps=0.2 "%s"', $video2, $destVideo2));

function runCommand($command)
{
  $stream = popen($command . ' 2>&1', 'r');
  while (!feof($stream))
  {
    echo fread($stream, 4096);
    flush();
  }
  pclose($stream);
}
