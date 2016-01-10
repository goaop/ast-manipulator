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
 * File filter defines a callback for checking the condition if file should be processed or not
 */
class FileFilter
{
    /**
     * Path to the root directory, where enumeration should start
     *
     * @var string
     */
    private $rootDirectory;

    /**
     * List of additional include paths, should be below rootDirectory
     *
     * @var array
     */
    private $includePaths;

    /**
     * List of additional exclude paths, should be below rootDirectory
     *
     * @var array
     */
    private $excludePaths;

    /**
     * Initializes an enumerator
     *
     * @param string $rootDirectory Path to the root directory
     * @param array $includePaths List of additional include paths
     * @param array $excludePaths List of additional exclude paths
     */
    public function __construct($rootDirectory, array $includePaths = array(), array $excludePaths = array())
    {
        $this->rootDirectory = $rootDirectory;
        $this->includePaths  = $includePaths;
        $this->excludePaths  = $excludePaths;
    }

    /**
     * Performs a check of file
     *
     * @param \SplFileInfo $file File to check
     *
     * @return bool
     */
    public function __invoke(\SplFileInfo $file)
    {
        $rootDirectory = $this->rootDirectory;
        $includePaths  = $this->includePaths;
        $excludePaths  = $this->excludePaths;

        if ($file->getExtension() !== 'php') {
            return false;
        };

        $realPath = $file->getRealPath();
        // Do not touch files that not under rootDirectory
        if (strpos($realPath, $rootDirectory) !== 0) {
            return false;
        }

        if ($includePaths) {
            $found = false;
            foreach ($includePaths as $includePath) {
                if (strpos($realPath, $includePath) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        foreach ($excludePaths as $excludePath) {
            if (strpos($realPath, $excludePath) === 0) {
                return false;
            }
        }

        return true;
    }
}
