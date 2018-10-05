<?php

/**
 * @category OVV
 * @package OVV_GeneratorCodeMinifier
 * @copyright Copyright (c) 2018 Ovakimyan Vazgen
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License ("OSL") v. 3.0
 * @author Ovakimyan Vazgen
 * @link https://www.linkedin.com/in/vdevx86/
 */

use Magento\Framework\Code\Generator\Io;

/**
 * DI and Interceptors are not working in this particular case
 * We're trying to include the Io.php file earlier so
 * the built-in autoloader wouldn't be triggered
 *
 * Use this code if you need to omit inclusion of the Io.php file:
 * define('GENERATOR_CODE_MINIFIER_INCLUDE', [
 *    \Magento\Framework\Code\Generator\Io::class => false
 * ]);
 */
if (
        !defined('GENERATOR_CODE_MINIFIER_INCLUDE')
    ||  !is_array(GENERATOR_CODE_MINIFIER_INCLUDE)
    ||  !isset(GENERATOR_CODE_MINIFIER_INCLUDE[Io::class])
    ||  GENERATOR_CODE_MINIFIER_INCLUDE[Io::class] !== false
) {
    include_once implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'src', 'Code', 'Generator', 'Io.php']);
}
