<?php
require_once('SagCache.php');
require_once('SagException.php');

/*
 * Cache to the local hard disk. Uses the system's default temp directory by
 * default, but you can specify another location.
 *
 * Cache keys are used for file names, and the contents are JSON. System file
 * sizes are used to calculate the cache's current size.
 *
 * @package Cache 
 * @version 0.2.0
 */
class SagFileCache extends SagCache 
{
  private static $fileExt = ".sag";

  private $fsLocation;

  /**
   * @param string $location The file system path to the directory that should
   * be used to store the cache files. The local system's temp directory is
   * used by default.
   * @return SagFileCache
   */
  public function SagFileCache($location)
  {
    if(!is_dir($location))
      throw new SagException("The provided cache location is not a directory.");

    if(!is_readable($location) || !is_writable($location))
      throw new SagException("Insufficient privileges to the supplied cache directory.");

    $this->fsLocation = rtrim($location, "/ \t\n\r\0\x0B");

    /* 
     * Just update - don't freak out if the size isn't right, as the user might
     * update it to non-default, they might do anything with the cache, they
     * might clean it themselves, etc. give them time.
     */
    foreach(glob($this->fsLocation."/*".self::$fileExt) as $file)
      $this->currentSize += filesize($file);
  }   

  /**
   * Generates the full filename/path that would be used for a given URL's
   * cache object.
   *
   * @param string $url The URL for the cached item.
   * @return string
   */
  private function makeFilename($url)
  {
    return "$this->fsLocation/".self::makeKey($url).self::$fileExt;
  }

  public function set($url, $item, $expiresOn = null)
  {
    if(
      empty($url) || 
      !is_int($expiresOn) || 
      (
        $expiresOn <= time() &&
        $expiresOn != null
      ) 
    )
      throw new SagException("Invalid parameters for caching.");

    $toCache = new StdClass();
    $toCache->e = ($expiresOn == null) ? self::$defaultExpiresOn : $expiresOn;
    $toCache->v = $item; 
    $toCache = json_encode($toCache);

    $file = self::makeFilename($url);

    //We don't allow symlinks, because when we recreate it won't be a symlink
    //any longer.
    if(file_exists($file) && is_file($file))
    {
      if(!is_readable($file) || !is_writable($file))
        throw new Exception("Could not read the cache file for URL: $url - please check your file system privileges.");

      $oldSize = filesize($file);
      if($this->currentSize - $oldSize + strlen($toCache) > $this->defaultSize)
        return false;

      $fh = fopen($file, "r+");

      $oldCopy = json_decode(fread($fh));

      ftruncate($fh);
      $this->currentSize -= $oldSize;

      unset($oldSize);

      rewind($fh);
    }
    else
    {
      $estSize = $this->currentSize + strlen($toCache);

      if($estSize >= disk_free_space("/") * .95)
        throw new Exception("Trying to cache to a disk with low free space - refusing to cache.");

      if($estSize > $this->defaultsize)
        return false;

      $fh = fopen($file, "w");
    }

    fwrite($fh, json_encode($toCache)); //don't throw up if we fail - we're not mission critical
    $this->currentSize += filesize($file);

    fclose($fh);

    return (is_object($oldCopy) && ($oldCopy->e == null || $oldCopy->e < time())) ? $oldCopy->v || true;
  }

  public function get($url)
  {

  }

  public function remove($url)
  {

  }

  public function clear()
  {

  }

  public function prune()
  {

  }
} 
?>