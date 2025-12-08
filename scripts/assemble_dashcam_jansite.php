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
    'Assemble Jansite dashcam videos (format: YYYYMMDD_HHIISSX.ts) (with X being [F]ront or [R]ear)',
    str_repeat('-', 30),
    'Usage:',
    '$ assemble_dascham path/to/ts/files',
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
$frontFiles = glob($dir . '/*F.{ts,mov}', GLOB_BRACE);
$frontCombinedFile = realpath($dir) . '/_front.ts';
combineFiles($frontFiles, $frontCombinedFile);

// Combine and inverse rear
$rearFiles = glob($dir . '/*R.{ts,mov}', GLOB_BRACE);
$rearCombinedFile = realpath($dir) . '/_rear.ts';
$rearCombinedPaddedFile = realpath($dir) . '/_rear_padded.mp4';
combineFiles($rearFiles, $rearCombinedFile, true);

// Overlay front & rear videos
if ($needsOverlay) {
  runCommand('ffmpeg ' . implode(' ', [
    '-i "' . $frontCombinedFile . '"',
    '-i "' . $rearCombinedFile . '"',
    '-filter_complex "[1:v]scale=854:480[overlay];[0:v][overlay]overlay=1686:20"',
    '-c:v libx264 -crf 15 -preset ultrafast',
    '"' . realpath($dir) . '/_overlayed.mp4"',
  ]));
}

// Stack rear & front videos
// (First, scale down rear to 720p and add horizontal padding to match front width)
if ($needsStack) {
  runCommand('ffmpeg ' . implode(' ', [
    '-i "' . $rearCombinedFile . '"',
    '-vf "scale=1280:720:force_original_aspect_ratio=decrease,pad=2560:720:(ow-iw)/2:(oh-ih)/2:color=black"',
    '-c:v libx264 -crf 15 -preset ultrafast',
    '"' . $rearCombinedPaddedFile . '"',
  ]));

  runCommand('ffmpeg ' . implode(' ', [
    '-i "' . $rearCombinedPaddedFile . '"',
    '-i "' . $frontCombinedFile . '"',
    '-filter_complex "[0:v][1:v]vstack=inputs=2[v]"',
    '-map "[v]"',
    '-c:v libx264 -crf 15 -preset ultrafast',
    '"' . realpath($dir) . '/_stacked.mp4"',
  ]));  
}

function combineFiles($filesList, $destFile, $needsFlip = false) {
  $filesListText = '';
  $filesListTextPath = str_replace('.ts', '.txt', $destFile);
  foreach($filesList as $file) {
    $filesListText .= 'file \'' . realpath($file) . '\'' . "\n";
  }
  file_put_contents($filesListTextPath, $filesListText);
  runCommand('ffmpeg ' . implode(' ', [
    '-f concat',
    '-an', // Drop all audio streams
    '-safe 0', // To avoid getting "unsafe filename" error on random files?
    '-i "' . $filesListTextPath . '"',
    $needsFlip ? '-vf "hflip"' : '', // Horizontal flip for rear view
    !$needsFlip ? '-c copy' : '', // Copy if flip isn't needed for faster output
    // h264 with low compression when flipping
    $needsFlip ? '-c:v libx264 -crf 15 -preset ultrafast' : '',
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
