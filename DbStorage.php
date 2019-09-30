<?php
/**
 * empty class to extend DbStorage in console
 */
if(!class_exists("DbStorage")) {
  if (class_exists("\LimeSurvey\PluginManager\DbStorage")) {
    class DbStorage extends \LimeSurvey\PluginManager\DbStorage {
    }
  } else {
    class DbStorage extends \ls\pluginmanager\DbStorage {
    }
  }
}
