<?php

/**
 * @category OVV
 * @package OVV_GeneratorCodeMinifier
 * @copyright Copyright (c) 2018 Ovakimyan Vazgen
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License ("OSL") v. 3.0
 * @author Ovakimyan Vazgen
 * @link https://www.linkedin.com/in/vdevx86/
 */

namespace Magento\Framework\Code\Generator;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Manages generated code
 */
class Io
{
    
    /**
     * Default code generation directory
     * Should correspond the value from \Magento\Framework\Filesystem
     */
    const DEFAULT_DIRECTORY = 'generated/code';
    
    /**
     * List of regular expressions for file names validation
     */
    const FILENAME_INVALID_PATTERNS = [
        '/^[A-Za-z][a-zA-Z0-9_]*ExtensionInterface\.php$/S'
    ];
    
    /**
     * Minimal token count
     */
    const TOKEN_MIN_COUNT = 8;
    
    /**
     * Path to directory where new file must be created
     *
     * @var string
     */
    protected $generationDirectory;
    
    /**
     * @var File
     */
    protected $filesystemDriver;
    
    /**
     * @param File $filesystemDriver
     * @param null|string $generationDirectory
     */
    public function __construct(
        File $filesystemDriver,
        $generationDirectory = null
    ) {
        $this->filesystemDriver = $filesystemDriver;
        $this->initGeneratorDirectory($generationDirectory);
    }
    
    /**
     * Get path to generation directory
     *
     * @param null|string $directory
     * @return void
     */
    protected function initGeneratorDirectory($directory = null)
    {
        if ($directory) {
            $this->generationDirectory = rtrim($directory, '/') . '/';
        } else {
            $this->generationDirectory = BP . '/' . self::DEFAULT_DIRECTORY;
        }
    }
    
    /**
     * @param string $className
     * @return string
     */
    public function getResultFileDirectory($className): string
    {
        $fileName = $this->generateResultFileName($className);
        $pathParts = explode('/', $fileName);
        unset($pathParts[count($pathParts) - 1]);
        return implode('/', $pathParts) . '/';
    }
    
    /**
     * @param string $className
     * @return string
     */
    public function generateResultFileName($className): string
    {
        return $this->generationDirectory . ltrim(strtr($className, ['\\' => '/', '_' => '/']), '/') . '.php';
    }
    
    /**
     * Get filename invalid patterns
     *
     * @return array
     */
    public function getFilenameInvalidPatterns(): array
    {
        $result = [];
        if (defined('GENERATOR_CODE_MINIFIER_FILENAME_INVALID_PATTERNS')) {
            $result = GENERATOR_CODE_MINIFIER_FILENAME_INVALID_PATTERNS;
        } else {
            $result = static::FILENAME_INVALID_PATTERNS;
        }
        return $result;
    }
    
    /**
     * Validate file name by list of regular expressions
     *
     * @param string $fileName
     * @return bool
     */
    public function validFileName(string $fileName): bool
    {
        $result = true;
        if ($invalidPatterns = $this->getFilenameInvalidPatterns()) {
            foreach ($invalidPatterns as $invalidPattern) {
                if (preg_match($invalidPattern, $fileName)) {
                    $result = false;
                    break;
                }
            }
        }
        return $result;
    }
    
    /**
     * Minify file
     *
     * @param string $fileName
     * @return string
     */
    public function minifyFile(string $fileName): string
    {
        $tokens = token_get_all(php_strip_whitespace($fileName));
        /**
         * Normalize token array
         * Handle HEREDOCs and NOWDOCs separately
         */
        $xdocMode = false;
        foreach ($tokens as $tokenKey => &$token) {
            if (!is_array($token)) {
                $token = [0, $token, 0];
            }
            $token[1] = trim($token[1]);
            if ($token[1] == '') {
                unset($tokens[$tokenKey]);
                continue;
            }
            if ($token[0] == T_START_HEREDOC) {
                $xdocMode = true;
            }
            if ($xdocMode) {
                $token[1] .= PHP_EOL;
            }
            if ($token[0] == T_END_HEREDOC) {
                $xdocMode = false;
            }
        }
        /**
         * Minify PHP code
         * If the last char of the previous token and the first
         * char of the current token are "sticky" - use PHP_EOL
         * as a separator; join tokens in any other case.
         * PHP_EOL usage as a separator instead of a space char
         * improves PHP files readability.
         * Variables:
         * $C: tokens count
         * $J: index of the previous token in the tokens array
         * $I: index of the current token in the tokens array
         * $A: the last char of the previous token
         * $B: the first char of the current token
         */
        if ($tokens) {
            $C = count($tokens);
            if ($C > self::TOKEN_MIN_COUNT) {
                $tokens = array_values($tokens);
                $result = $tokens[0][1];
                for ($I = 1, $J = 0; $I < $C; ++$I, ++$J) {
                    $A = ord($tokens[$J][1][strlen($tokens[$J][1]) - 1]);
                    $B = ord($tokens[$I][1][0]);
                    if (
                        (
                                ($A > 0x2F && $A < 0x3A)
                            ||  ($A > 0x40 && $A < 0x5B)
                            ||  ($A > 0x60 && $A < 0x7B)
                            ||  ($A == 0x5F)
                        )
                            &&
                        (
                                ($B > 0x2F && $B < 0x3A)
                            ||  ($B > 0x40 && $B < 0x5B)
                            ||  ($B > 0x60 && $B < 0x7B)
                            ||  ($B == 0x5F)
                        )
                    ) {
                        $result .= PHP_EOL;
                    }
                    $result .= $tokens[$I][1];
                }
            }
        }
        return $result ?? '';
    }
    
    /**
     * @param string $fileName
     * @param string $content
     * @throws FileSystemException
     * @return bool
     */
    public function writeResultFile($fileName, $content): bool
    {
        $content = '<?php ' . $content;
        $tmpFile = $fileName . '.' . getmypid();
        $this->filesystemDriver->filePutContents($tmpFile, $content);
        if ($this->validFileName(basename($fileName))) {
            if ($content = $this->minifyFile($tmpFile)) {
                $this->filesystemDriver->filePutContents($tmpFile, $content);
            }
        }
        try {
            /**
             * Rename is atomic on *nix systems, while file_put_contents is not. Writing to a
             * temporary file whose name is process-unique and renaming to the real location helps
             * avoid race conditions. Race condition can occur if the compiler has not been run, when
             * multiple processes are attempting to access the generated file simultaneously.
             */
            $success = $this->filesystemDriver->rename($tmpFile, $fileName);
        } catch (FileSystemException $e) {
            if (!$this->fileExists($fileName)) {
                throw $e;
            } else {
                /**
                 * Due to race conditions, file may have already been written, causing rename to fail. As long as
                 * the file exists, everything is okay.
                 */
                $success = true;
            }
        }
        return $success;
    }
    
    /**
     * @return bool
     */
    public function makeGenerationDirectory(): bool
    {
        return $this->makeDirectory($this->generationDirectory);
    }
    
    /**
     * @param string $className
     * @return bool
     */
    public function makeResultFileDirectory($className): bool
    {
        return $this->makeDirectory($this->getResultFileDirectory($className));
    }
    
    /**
     * @return string
     */
    public function getGenerationDirectory(): string
    {
        return $this->generationDirectory;
    }
    
    /**
     * @param string $fileName
     * @return bool
     */
    public function fileExists($fileName): bool
    {
        return $this->filesystemDriver->isExists($fileName);
    }
    
    /**
     * Wrapper for include
     *
     * @param string $fileName
     * @return mixed
     * @codeCoverageIgnore
     */
    public function includeFile($fileName)
    {
        return include $fileName;
    }
    
    /**
     * @param string $directory
     * @return bool
     */
    protected function makeDirectory(string $directory): bool
    {
        if ($this->filesystemDriver->isWritable($directory)) {
            return true;
        }
        try {
            if (!$this->filesystemDriver->isDirectory($directory)) {
                $this->filesystemDriver->createDirectory($directory);
            }
            return true;
        } catch (FileSystemException $e) {
            return false;
        }
    }
    
}
