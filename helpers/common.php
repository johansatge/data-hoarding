<?php

$GLOBALS['_script_start'] = microtime(true);

register_shutdown_function(function() {
  $elapsed = microtime(true) - $GLOBALS['_script_start'];
  if ($elapsed < 5) return;
  $scriptName = basename($_SERVER['SCRIPT_FILENAME'], '.php');
  $icon = 'file://' . __DIR__ . '/../assets/icon.png';
  exec(implode(' ', [
    '/opt/homebrew/bin/terminal-notifier',
    '-title ' . escapeshellarg($scriptName),
    '-message ' . escapeshellarg('Done in ' . round($elapsed) . 's'),
    '-sender com.apple.Terminal',
    '-contentImage ' . escapeshellarg($icon),
    '-sound default',
    '> /dev/null 2>&1',
  ]));
});

function printHelpAndExit(array ...$sections) {
  $sep = str_repeat('-', 30);
  $parts = array_map(fn($s) => implode("\n", $s), $sections);
  echo $sep . "\n" . implode("\n" . $sep . "\n", $parts) . "\n" . $sep . "\n";
  exit(0);
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
