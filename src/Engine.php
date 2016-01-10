<?php
/**
 * AST Manipulator API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\AstManipulator;

use Go\AstManipulator\SourceTransformer\AstSourceFilter;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor as ExtensionInterface;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinterStandard;
use PhpParser\PrettyPrinterAbstract;

class Engine
{
    /**
     * Source code lexer
     *
     * @var Lexer
     */
    protected static $lexer;

    /**
     * Instance of PHP source code parser
     *
     * @var Parser
     */
    private static $parser = null;

    /**
     * Pretty printer for transformed nodes
     *
     * @var PrettyPrinterAbstract
     */
    private static $printer;

    /**
     * Node traverser
     *
     * @var NodeTraverser
     */
    private static $traverser = null;

    /**
     * Registers an extension
     *
     * @param string|ExtensionInterface $extension Instance of extension to register or class name
     */
    public static function registerExtension($extension)
    {
        $instance = null;

        if ($extension instanceof ExtensionInterface) {
            $instance = $extension;
        } elseif (is_string($extension)) {
            $extensionReflection = new \ReflectionClass($extension);
            if (!$extensionReflection->isSubclassOf(ExtensionInterface::class)) {
                throw new \InvalidArgumentException("Extension should be an instance of " . ExtensionInterface::class);
            }
            $instance = $extensionReflection->newInstance();
        }

        if (!isset($instance)) {
            throw new \InvalidArgumentException("Extension should be an instance of " . ExtensionInterface::class);
        }

        self::$traverser->addVisitor($instance);
    }

    /**
     * Unregisters an extension from the engine
     *
     * @param ExtensionInterface $extension Extension to unregister
     */
    public static function unregisterExtension(ExtensionInterface $extension)
    {
        self::$traverser->removeVisitor($extension);
    }

    /**
     * Parses a content of the file and returns a transformed one
     *
     * @param string $content Source code to parse
     *
     * @return string Transformed source code
     */
    public static function parse($content)
    {
        $astNodes = self::$parser->parse($content);
        $astNodes = self::$traverser->traverse($astNodes);

        $content  = self::$printer->prettyPrintFile($astNodes);

        return $content;
    }

    /**
     * Performs initialization of library
     */
    public static function init()
    {
        self::$lexer     = new Lexer();
        self::$parser    = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, self::$lexer);
        self::$traverser = new NodeTraverser();
        self::$printer   = new PrettyPrinterStandard();

        AstSourceFilter::register();
    }

    /**
     * Checks, if file should be processed or not
     *
     * @param string $file Name of the file to process
     * @todo Implement the logic of filtration
     *
     * @return bool
     */
    public static function shouldProcess($file)
    {
        return strpos($file, 'Demo') !== false;
    }
}
