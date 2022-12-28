<?php

function parse_argv()
{
  $args = ['_' => []];
  global $argv;
  foreach(array_slice($argv, 1) as $arg)
  {
    preg_match_all('#^--([^=]+)=?(.*)$#', $arg, $matches);
    if (!empty($matches[1][0]))
    {
      $args[$matches[1][0]] = isset($matches[2][0]) && strlen($matches[2][0]) > 0 ? $matches[2][0] : true;
    }
    else
    {
      $args['_'][] = $arg;
    }
  }
  return $args;
}
