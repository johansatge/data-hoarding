<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();

if (!empty($args['help']) || count($args['_']) === 0) {
  echo implode("\n", [
    str_repeat('-', 30),
    'Compress images (jpeg 85%) without stripping EXIF tags',
    '(Compressing a RAW image will generate a corresponding jpeg file, and keep the original)',
    str_repeat('-', 30),
    'Usage:',
    '$ compress_image file1.jpg file2.jpg file3.pef file4.dng',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$jpegFiles = [];
$rawFiles = [];

foreach($args['_'] as $arg) {
  $pathinfo = pathinfo($arg);
  if ($pathinfo['extension'] === 'jpg') {
    $jpegFiles[] = $arg;
  }
  else if (in_array($pathinfo['extension'], ['dng', 'pef', 'raw'])) {
    $rawFiles[] = $arg;
  }
}

// Convert RAW files to JPEG first
foreach($rawFiles as $arg) {
  convertRawToJpeg($arg);
  $jpegFiles[] = preg_replace('#\.(dng|pef|raw)$#', '.jpg', $arg);
}

// Compress all JPEGs in one batch
if (!empty($jpegFiles)) {
  compressJpeg($jpegFiles);
}

function convertRawToJpeg($filePath) {
  $destFilePath = preg_replace('#\.(dng|pef|raw)$#', '.jpg', $filePath);
  if (is_readable($destFilePath)) {
    echo '⚠️ ' . $destFilePath . ' already exists, skipping' . "\n";
    return null;
  }
  runCommand('sips -s format jpeg "' . $filePath . '" -s formatOptions 100 --out "' . $destFilePath . '"');
}

function compressJpeg($filePaths) {
  // Use a custom build of jpegoptim (from ImageOptim using mozjpeg) for better results
  // Context in https://github.com/ImageOptim/ImageOptim/issues/102#issuecomment-327311201
  // @todo compile a more recent version to have --threads support
  $jpegoptimBin = __DIR__ . '/../bin/jpegoptim';
  
  // Build command with all files at once
  // Compress if it saves at least 2% of file size
  $fileList = implode('" "', $filePaths);
  runCommand('"' . $jpegoptimBin . '" --max=85 --strip-none --threshold=2 --totals "' . $fileList . '"');
}

function runCommand($command) {
  $stream = popen($command . ' 2>&1', 'r');
  while (!feof($stream)) {
    echo fread($stream, 4096);
    flush();
  }
  pclose($stream);
}