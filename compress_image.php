<?php

require('parse_argv.php');
$args = parse_argv();

if (!empty($args['help']) || count($args['_']) === 0)
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Compress JPEG images in place, without stripping EXIF tags',
    str_repeat('-', 30),
    'Usage:',
    '$ compress_image file1.jpg file2.jpg',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$command = 'jpegoptim --max=85 --strip-none --totals ' . implode(' ', $args['_']);
$stream = popen($command . ' 2>&1', 'r');
while (!feof($stream))
{
  echo fread($stream, 4096);
  flush();
}
pclose($stream);
