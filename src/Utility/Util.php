<?php
class Util
{
  public static function clearCookie($name): void
  {
    setcookie($name, '', 1);
    $_COOKIE[$name] = '';
  }
}
