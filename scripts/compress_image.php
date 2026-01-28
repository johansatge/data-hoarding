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

foreach($args['_'] as $arg) {
  $pathinfo = pathinfo($arg);
  if ($pathinfo['extension'] === 'jpg') {
    compressJpeg($arg);
  }
  else if (in_array($pathinfo['extension'], ['dng', 'pef', 'raw'])) {
    convertRawToJpeg($arg);
  }
}

function convertRawToJpeg($filePath) {
  $destFilePath = preg_replace('#\.(dng|pef|raw)$#', '.jpg', $filePath);
  if (is_readable($destFilePath)) {
    echo '⚠️ ' . $destFilePath . ' already exists, skipping' . "\n";
    return null;
  }
  runCommand('sips -s format jpeg "' . $filePath . '" -s formatOptions 100 --out "' . $destFilePath . '"');
  compressJpeg($destFilePath);
}

// @todo run all images through the same jpegoptim instance? with parallel threads, see jpegoptim options
function compressJpeg($filePath) {
  // Use a custom build of jpegoptim (from ImageOptim using mozjpeg) for better results
  // Context in https://github.com/ImageOptim/ImageOptim/issues/102#issuecomment-327311201
  // @todo compile a more recent version
  $jpegoptimBin = __DIR__ . '/../bin/jpegoptim';
  runCommand('"' . $jpegoptimBin . '" --max=85 --strip-none --totals "' . $filePath . '"');
}

function runCommand($command) {
  $stream = popen($command . ' 2>&1', 'r');
  while (!feof($stream)) {
    echo fread($stream, 4096);
    flush();
  }
  pclose($stream);
}