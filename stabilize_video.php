<?php

require('parse_argv.php');
$args = parse_argv();
$input = count($args['_']) > 0 ? $args['_'][0] : '';

$stab = new StabilizeVideo($input, $args);
$stab->do();

class StabilizeVideo
{

  private $doAnalyze = false;
  private $doStabilize = false;
  private $doCompare = false;

  private $smoothing = 10;
  private $shakiness = 5;
  private $accuracy = 15;

  private $path;
  private $pathAnalyze;
  private $pathStabilize;
  private $pathCompare;

  public function __construct($input, $args)
  {
    $this->doAnalyze = !empty($args['analyze']) ? $args['analyze'] : false;
    $this->doStabilize = !empty($args['stabilize']) ? $args['stabilize'] : false;
    $this->doCompare = !empty($args['compare']) ? $args['compare'] : false;

    $this->smoothing = !empty($args['smoothing']) ? intval($args['smoothing']) : $this->smoothing;
    $this->shakiness = !empty($args['shakiness']) ? intval($args['shakiness']) : $this->shakiness;
    $this->accuracy = !empty($args['accuracy']) ? intval($args['accuracy']) : $this->accuracy;

    $this->path = $input;
    $this->pathAnalyze = $this->path . '.trf';
    $this->pathStabilize = preg_replace('#\.(mov|mp4)$#i', '.stab.$1', $this->path);
    $this->pathCompare = preg_replace('#\.(mov|mp4)$#i', '.compare.$1', $this->path);
  }

  public function do()
  {
    if (empty($this->path))
    {
      echo 'Input file needed' . "\n";
      exit(1);
    }
    if ($this->doAnalyze)
    {
      $command = 'ffmpeg -i "%s" -vf vidstabdetect=result="%s":shakiness=%d:accuracy=%d -f null -';
      $this->execCommand(sprintf($command, $this->path, $this->pathAnalyze, $this->shakiness, $this->accuracy));
    }
    if ($this->doStabilize)
    {
      $command = 'ffmpeg -i "%s" -vf vidstabtransform=smoothing=%d:input="%s" %s';
      $this->execCommand(sprintf($command, $this->path, $this->smoothing, $this->pathAnalyze, $this->pathStabilize));
    }
    if ($this->doCompare)
    {
      $command = 'ffmpeg -i "%s" -i "%s" -filter_complex "[0:v:0]pad=iw*2:ih[bg]; [bg][1:v:0]overlay=w" %s';
      $this->execCommand(sprintf($command, $this->path, $this->pathStabilize, $this->pathCompare));
    }
    exit(0);
  }

  private function execCommand($command)
  {
    $stream = popen($command, 'r');
    while (!feof($stream))
    {
      echo fread($stream, 4096);
      flush();
    }
    pclose($stream);
  }
}
