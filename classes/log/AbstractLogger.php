<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 *  @author    thirty bees <contact@thirtybees.com>
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2017-2024 thirty bees
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class AbstractLoggerCore
 */
abstract class AbstractLoggerCore
{
    /**
     * @var int
     */
    public $level;

    /**
     * @var string[]
     */
    protected $level_value = [
        0 => 'DEBUG',
        1 => 'INFO',
        2 => 'WARNING',
        3 => 'ERROR',
    ];

    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;

    /**
     * AbstractLoggerCore constructor.
     *
     * @param int $level
     */
    public function __construct($level = self::INFO)
    {
        if (array_key_exists((int) $level, $this->level_value)) {
            $this->level = $level;
        } else {
            $this->level = static::INFO;
        }
    }

    /**
     * Check the level and log the message if needed
     *
     * @param string $message
     * @param int $level
     */
    public function log($message, $level = self::DEBUG)
    {
        if ($level >= $this->level) {
            $this->logMessage($message, $level);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message
     */
    public function logDebug($message)
    {
        $this->log($message, static::DEBUG);
    }

    /**
     * Log an info message
     *
     * @param string $message
     */
    public function logInfo($message)
    {
        $this->log($message, static::INFO);
    }

    /**
     * Log a warning message
     *
     * @param string $message
     */
    public function logWarning($message)
    {
        $this->log($message, static::WARNING);
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    public function logError($message)
    {
        $this->log($message, static::ERROR);
    }

    /**
     * Log the message
     *
     * @param string $message
     * @param int $level
     */
    abstract protected function logMessage($message, $level);
}
