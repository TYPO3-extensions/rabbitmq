<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Bastian Bringenberg <typo3@bastian-bringenberg.de>, Bastian Bringenberg
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Channel/AbstractChannel.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Wire/GenericContent.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Wire/AMQPReader.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Wire/AMQPWriter.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Helper/Protocol/FrameBuilder.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Connection/AMQPConnection.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Channel/AMQPChannel.php');
require_once(__DIR__.'/../../Resources/Private/PhpAmqpLib/Message/AMQPMessage.php');

use PhpAmqpLib\Connection;
/**
 *
 *
 * @package rabbitmq
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @todo create possibility for not persistent Queues and Messages
 */
class Tx_Rabbitmq_Service_Queue {
        /**
        * @var AMQPConnection
        */
        private $conn;

        /**
        * @var Tx_Extbase_Configuration_ConfigurationManagerInterface
        */
        protected $configurationManager;

        /**
        * @param Tx_Extbase_Configuration_ConfigurationManagerInterface $configurationManager
        * @return void
        */
        public function injectConfigurationManager(Tx_Extbase_Configuration_ConfigurationManagerInterface $configurationManager) {
            $this->configurationManager = $configurationManager;
        }

    /**
     * Creates a new Element of the RabbitMQ Queue and connects to the Server
     * If you enter a $vHost the vHost out of the TypoScript will be overwritten
     *
     * @var string $vHost
     * @return Tx_Rabbitmq_Service_Queue
     */
    public function __construct($vHost = NULL) {
        $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_rabbitmq.']['login.'];
        $vhost = $conf['vhost'];
        if($vHost != NULL ) $vhost = $vHost;
        $this->conn = new \PhpAmqpLib\Connection\AMQPConnection($conf['host'], $conf['port'], $conf['user'], $conf['password'], $vhost);
    }

    /**
     * Destructor Closes the connection to RabbitMQ
     */
    function __destruct() {
        $this->conn->close();
    }

    /**
     * Pushes a Message at the end of the RabbitMQ
     *
     * @param string $channelID
     * @param string $message
     * @return void
     */
    public function writeMessage($channelID, $message) {
        $channel = $this->conn->channel();
        $channel->queue_declare($channelID, FALSE, TRUE, FALSE, FALSE);
        $msg = new \PhpAmqpLib\Message\AMQPMessage($message, array('delivery_mode' => '2'));
        $channel->basic_publish($msg, '', $channelID);
        $channel->close();
    }

    /**
     * This will read the Messages out of the queue and give them as parameter for $callback
     * If you want
     *
     * __Warning:__ If you readMessages your script will
     *
     * @param $channelID
     * @param $callback
     * @return void
     */
    public function readMessage($channelID, $callback) {
        $channel = $this->conn->channel();
        $channel->queue_declare($channelID, FALSE, TRUE, FALSE, FALSE);
        $channel->basic_consume($channelID, '', FALSE, TRUE, FALSE, FALSE, $callback);
        while(count($channel->callbacks)) {
            $channel->wait();
        }
        $channel->close();
    }
}