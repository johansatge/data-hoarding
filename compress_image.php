<?php

require('parse_argv.php');
$args = parse_argv();

if (count($args['_']) === 0)
{
  echo 'At least one source file needed' . "\n";
  exit(1);
}

$command = 'jpegoptim --max=85 --strip-none --totals ' . implode(' ', $args['_']);
$stream = popen($command . ' 2>&1', 'r');
while (!feof($stream))
{
  echo fread($stream, 4096);
  flush();
}
pclose($stream);
