<?php
/**
 * AST Manipulator API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\AstManipulator\Hook;

use Composer\Autoload\ClassLoader;
use Go\AstManipulator\Engine;
use Go\AstManipulator\SourceTransformer\AstSourceFilter;

/**
 * Composer wrapper hook implementation for handling files loaded via composer autoloading mechanism
 */
class ComposerHook
{
    /**
     * Is hook working in production mode or not
     *
     * @var bool
     */
    protected static $isProductionMode;

    /**
     * Instance of original autoloader
     *
     * @var ClassLoader
     */
    protected $original = null;

    /**
     * Cache state
     *
     * @var array
     */
    private $cacheState;

    /**
     * Constructs an wrapper for the composer loader
     *
     * @param ClassLoader $original Instance of current loader
     */
    public function __construct(ClassLoader $original)
    {
        $this->original   = $original;
        $this->cacheState = array(); // todo: implement cache state loading or integration with classmap
    }

    /**
     * Activates composer autoloader hook
     *
     * @param bool $isProductionMode Is application running in the production mode or not
     */
    public static function activateHook($isProductionMode = true)
    {
        $isSuccessful = false;
        $loaders      = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $isSuccessful = true;
                $loader[0]    = new ComposerHook($loader[0]);
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        if (!$isSuccessful) {
            throw new \RuntimeException("Composer hook was not activated");
        }

        self::$isProductionMode = $isProductionMode;
    }

    /**
     * Deactivates composer autoloader hook
     */
    public static function deactivateHook()
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof static)) {
                $loader[0] = $loader[0]->original;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }
    }

    /**
     * Loads the given class or interface.
     *
     * @param  string    $class The name of the class
     * @return bool|null True if loaded, null otherwise
     *
     * @see \Composer\Autoload\ClassLoader::loadClass
     */
    public function loadClass($class)
    {
        $file = $this->findFile($class);

        if ($file) {
            include $file;
        }
    }

    /**
     * Finds the path to the file where the class is defined.
     *
     * @param string $class The name of the class
     *
     * @return string|false The path if found, false otherwise
     *
     * @see \Composer\Autoload\ClassLoader::findFile
     */
    public function findFile($class)
    {
        $file = $this->original->findFile($class);

        if ($file) {
            $cacheState = isset($this->cacheState[$file]) ? $this->cacheState[$file] : null;
            if ($cacheState && self::$isProductionMode) {
                $file = $cacheState['cacheUri'] ?: $file;
            } elseif (Engine::shouldProcess($file)) {
                // can be optimized here with $cacheState even for debug mode, but no needed right now
                $file = AstSourceFilter::getTransformedSourcePath($file);
            }
        }

        return $file;
    }
}
