<?php

/**
 * @category OVV
 * @package OVV_GeneratorCodeMinifier
 * @copyright Copyright (c) 2018 Ovakimyan Vazgen
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License ("OSL") v. 3.0
 * @author Ovakimyan Vazgen
 * @link https://www.linkedin.com/in/vdevx86/
 */

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'OVV_GeneratorCodeMinifier',
    __DIR__
);

/**
 * Separate bootstrap code, exclude compilation errors
 */
include_once implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'etc', 'bootstrap.php']);
