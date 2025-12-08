<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();

$needsStack = !empty($args['stack']);
$needsOverlay = !empty($args['overlay']);

if (!empty($args['help']) || count($args['_']) === 0 || (!$needsStack && !$needsOverlay))
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Assemble Viofo dashcam videos (format: YYYY_MMDD_HHIISS_XXXXZ.MP4) (with XXXX being a numeric index and Z being [F]ront or [R]ear)',
    str_repeat('-', 30),
    'Usage:',
    '$ assemble_dascham_viofo path/to/mp4/files',
    str_repeat('-', 30),
    'Options:',
    '--stack    Stack vertically front and rear videos',
    '--overlay  Overlay rear on top of the right left corner of the front',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$dir = $args['_'][0];

// Combine front
$frontFiles = glob($dir . '/*F.MP4', GLOB_BRACE);
$frontCombinedFile = realpath($dir) . '/_front.mp4';
combineFiles($frontFiles, $frontCombinedFile);

// Combine rear
$rearFiles = glob($dir . '/*R.MP4', GLOB_BRACE);
$rearCombinedFile = realpath($dir) . '/_rear.mp4';
$rearCombinedPaddedFile = realpath($dir) . '/_rear_padded.mp4';
combineFiles($rearFiles, $rearCombinedFile);

// Overlay front & rear videos
if ($needsOverlay) {
  runCommand('ffmpeg ' . implode(' ', [
    '-i "' . $frontCombinedFile . '"',
    '-i "' . $rearCombinedFile . '"',
    '-filter_complex "[1:v]scale=854:480[overlay];[0:v][overlay]overlay=1686:20"',
    '-c:v libx264 -crf 15 -preset ultrafast',
    '"' . realpath($dir) . '/_overlayed.mp4"',
  ]));

  unlink($frontCombinedFile);
  unlink($rearCombinedFile);
}

// Stack rear & front videos
// (First, scale rear to 1080p and add horizontal padding to match front width)
if ($needsStack) {
  // Check rear video dimensions
  $probe = shell_exec('ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 "' . $rearCombinedFile . '"');
  list($rearWidth, $rearHeight) = explode('x', trim($probe));
  
  if ($rearWidth === 1920 && $rearHeight === 1080) {
    // Video is already 1080p, just add padding without re-encoding
    runCommand('ffmpeg ' . implode(' ', [
      '-i "' . $rearCombinedFile . '"',
      '-vf "pad=2560:1080:(ow-iw)/2:(oh-ih)/2:color=black"',
      '-c:v libx264 -crf 18 -preset fast',
      '"' . $rearCombinedPaddedFile . '"',
    ]));
  } else {
    // Need to scale to 1080p and add padding
    runCommand('ffmpeg ' . implode(' ', [
      '-i "' . $rearCombinedFile . '"',
      '-vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=2560:1080:(ow-iw)/2:(oh-ih)/2:color=black"',
      '-c:v libx264 -crf 18 -preset fast',
      '"' . $rearCombinedPaddedFile . '"',
    ]));
  }

  runCommand('ffmpeg ' . implode(' ', [
    '-i "' . $rearCombinedPaddedFile . '"',
    '-i "' . $frontCombinedFile . '"',
    '-filter_complex "[0:v][1:v]vstack=inputs=2[v]"',
    '-map "[v]"',
    '-c:v libx264 -crf 15 -preset ultrafast',
    '"' . realpath($dir) . '/_stacked.mp4"',
  ]));

  unlink($frontCombinedFile);
  unlink($rearCombinedFile);
  unlink($rearCombinedPaddedFile);
}

function combineFiles($filesList, $destFile) {
  $filesListText = '';
  $filesListTextPath = str_replace('.mp4', '.txt', $destFile);
  foreach($filesList as $file) {
    $filesListText .= 'file \'' . realpath($file) . '\'\'' . "\n";
  }
  file_put_contents($filesListTextPath, $filesListText);
  runCommand('ffmpeg ' . implode(' ', [
    '-f concat',
    '-an', // Drop all audio streams
    '-safe 0', // To avoid getting "unsafe filename" error on random files?
    '-i "' . $filesListTextPath . '"',
    '-c copy', // Copy streams for faster output
    '"' . $destFile . '"',
  ]));
  unlink($filesListTextPath);
}

function runCommand($command) {
  echo str_repeat('-', 20) . "\n";
  echo 'Running ' . $command . "\n";
  echo str_repeat('-', 20) . "\n";
  $stream = popen($command . ' 2>&1', 'r');
  while (!feof($stream)) {
    $stdout = fread($stream, 4096);
    flush();
    echo $stdout;
  }
  $code = pclose($stream);
  echo 'Exited with code ' . $code . "\n";
  if ($code !== 0) {
    exit($code);
  }
}
