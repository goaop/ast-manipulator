<?php
/**
 * AST Manipulator API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\AstManipulator\FileSystem;

/**
 * Class that manages real-code to cached-code paths mapping.
 * Can be extended to get a more sophisticated real-to-cached code mapping
 */
class PathManager
{
    /**
     * Name of the file with cache paths
     */
    const CACHE_FILE_NAME = '/_file.path.cache';

    /**
     * @var string|null
     */
    protected $cacheDirectory;

    /**
     * @var string|null
     */
    protected $rootDirectory;

    /**
     * Cached metadata for transformation state for the concrete file
     *
     * @var array
     */
    protected $cacheState = array();

    /**
     * New metadata items, that was not present in $cacheState
     *
     * @var array
     */
    protected $newCacheState = array();

    public function __construct($rootDirectory, $cacheDirectory)
    {
        $this->rootDirectory  = $rootDirectory;
        $this->cacheDirectory = $cacheDirectory;

        if ($this->cacheDirectory) {
            if (!is_dir($this->cacheDirectory)) {
                $cacheRootDir = dirname($this->cacheDirectory);
                if (!is_writable($cacheRootDir) || !is_dir($cacheRootDir)) {
                    throw new \InvalidArgumentException(
                        "Can not create a directory {$this->cacheDirectory} for the cache.
                        Parent directory {$cacheRootDir} is not writable or not exist.");
                }
                mkdir($this->cacheDirectory, 0770);
            }

            if (file_exists($this->cacheDirectory. self::CACHE_FILE_NAME)) {
                $this->cacheState = include $this->cacheDirectory . self::CACHE_FILE_NAME;
            }
        }
    }

    /**
     * Returns current cache directory for aspects, can be bull
     *
     * @return null|string
     */
    public function getCacheDirectory()
    {
        return $this->cacheDirectory;
    }

    /**
     * Configures a new cache directory for aspects
     *
     * @param string $cacheDirectory New cache directory
     */
    public function setCacheDirectory($cacheDirectory)
    {
        $this->cacheDirectory = $cacheDirectory;
    }

    /**
     * @param string $resource
     * @return bool|string
     */
    public function getCachePathForResource($resource)
    {
        if (!$this->cacheDirectory) {
            return false;
        }

        return str_replace($this->rootDirectory, $this->cacheDirectory, $resource);
    }

    /**
     * Tries to return an information for queried resource
     *
     * @param string|null $resource Name of the file or null to get all information
     *
     * @return array|null Information or null if no record in the cache
     */
    public function queryCacheState($resource=null)
    {
        if (!$resource) {
            return $this->cacheState;
        }

        if (isset($this->newCacheState[$resource])) {
            return $this->newCacheState[$resource];
        }

        if (isset($this->cacheState[$resource])) {
            return $this->cacheState[$resource];
        }

        return null;
    }

    /**
     * Put a record about some resource in the cache
     *
     * This data will be persisted during object destruction
     *
     * @param string $resource Name of the file
     * @param array $metadata Miscellaneous information about resource
     */
    public function setCacheState($resource, array $metadata)
    {
        $this->newCacheState[$resource] = $metadata;
    }

    /**
     * Automatic destructor saves all new changes into the cache
     *
     * This implementation is not thread-safe, so be care
     */
    public function __destruct()
    {
        $this->flushCacheState();
    }

    /**
     * Flushes the cache state into the file
     */
    public function flushCacheState()
    {
        if ($this->newCacheState) {
            $fullCacheMap = $this->newCacheState + $this->cacheState;
            $cachePath    = substr(var_export($this->cacheDirectory, true), 1, -1);
            $rootPath     = substr(var_export($this->rootDirectory, true), 1, -1);
            $cacheData    = '<?php return ' . var_export($fullCacheMap, true) . ';';
            $cacheData    = strtr($cacheData, array(
                '\'' . $cachePath => 'AST_CACHE_DIR . \'',
                '\'' . $rootPath  => 'AST_ROOT_DIR . \''
            ));
            file_put_contents($this->cacheDirectory . self::CACHE_FILE_NAME, $cacheData);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($this->cacheDirectory . self::CACHE_FILE_NAME, true);
            }
            $this->cacheState    = $this->newCacheState + $this->cacheState;
            $this->newCacheState = array();
        }
    }
}
