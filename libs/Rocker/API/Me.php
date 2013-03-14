<?php
namespace Rocker\API;

use Fridge\DBAL\Connection\ConnectionInterface;
use Rocker\REST\AbstractOperation;
use Rocker\Cache\CacheInterface;
use Rocker\REST\OperationResponse;
use Rocker\Server;
use Slim\Slim;

/**
 * Returns the user data of the user that authenticates the request
 *
 * @package Rocker\API
 * @author Victor Jonsson (http://victorjonsson.se)
 * @license MIT license (http://opensource.org/licenses/MIT)
 */
class Me extends AbstractOperation {

    /**
     * @inheritdoc
     */
    public function exec(Server $server, ConnectionInterface $db, CacheInterface $cache)
    {
        return new OperationResponse(200, $this->user->toArray());
    }

    /**
     * @inheritDoc
     */
    public function requiresAuth()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function allowedMethods()
    {
        return array('GET');
    }
}