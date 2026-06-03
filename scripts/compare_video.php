<?php

date_default_timezone_set('Europe/Paris');

require(__DIR__ . '/../helpers/parse_argv.php');
require(__DIR__ . '/../helpers/common.php');
$args = parse_argv();

if (!empty($args['help']) || count($args['_']) < 2) {
  printHelpAndExit(
    ['Compare multiple videos by extracting frames in the specified directory'],
    ['Usage:', '$ compare_video file1.mp4 file2.mp4 [file3.mp4 ...] destination/directory']
  );
}

// Last argument is the destination directory
$destPath = rtrim(array_pop($args['_']), '/');
$videos = $args['_'];
if (file_exists($destPath)) {
  echo "Error: Destination path already exists: $destPath\n";
  exit(1);
}

mkdir($destPath, 0755, true);

foreach ($videos as $index => $video) {
  $filename = pathinfo($video, PATHINFO_FILENAME);
  $destVideo = $destPath . '/%04d_' . $index . '_' . $filename . '.bmp';
  runCommand(sprintf('ffmpeg -i "%s" -vf fps=0.2 "%s"', $video, $destVideo));
}
