<?php
namespace Rocker\Console\Method;

use Rocker\REST\Client;
use Rocker\Utils\Security\RC4Cipher;
use Rocker\Console\Utils;


/**
 * Console method used to store information about remote Rocker servers
 *
 * @package rocker/server
 * @author Victor Jonsson (http://victorjonsson.se)
 * @license MIT license (http://opensource.org/licenses/MIT)
 */
class Server implements MethodInterface {

    /**
     * @var null|string
     */
    private $infoFile;

    /**
     * @param null $f
     */
    public function __construct($f = null)
    {
        $this->infoFile = $f === null ? $_SERVER['HOME'].'/.rocker-servers':$f;
    }

    /**
     * @inheritdoc
     */
    public function help()
    {
        $_ = function($str) { \cli\line($str); };
        $_('%_Method - server%n');
        $_('---------------------------------');
        $_('Options:');
        $_('  -l    List available servers');
        $_('  -r    Remove a server (eg. $ rocker -r my-server)');
        $_('  -d    Set one of the added servers as default (eg. $ rocker -d my-server)');
        $_('  -c    Check version of PHP-Rocker on remote server and available API operations');
        $_('Default:');
        $_('  Add or edit a remote server connection');
    }

    /**
     * @param array $servers
     */
    private function listServers($servers)
    {
        $default = '';
        if( isset($servers['__default']) ) {
            $default = $servers['__default'];
            unset($servers['__default']);
        }
        $table = new \cli\Table();
        $table->setHeaders(array('Name', 'Host', 'Auth mechanism'));
        foreach($servers as $name => $data) {
            if( $default == $name ) {
                $name .= ' (default)';
            }
            $table->addRow(array($name, $data['address'], current( explode(' ', $data['auth']))));
        }
        $table->display();
        \cli\out($default);
    }

    /**
     * @inheritdoc
     */
    public function call($args, $flags)
    {
        // Check version and operations on remote server
        if( in_array( '-c', $flags) )  {
            $client = self::loadClient($args);
            \cli\line('%_Base URI:%n '.$client->getBaseURI());
            \cli\line('%_Version:%n '.$client->serverVersion());
            \cli\line('%_Operations:%n');
            $table = new \cli\Table();
            $table->setHeaders(array('Class', 'Methods', 'Path'));
            foreach($client->request('GET', 'operations')->body as $data) {
                $table->addRow(array_values( (array)$data ));
            }
            $table->display();
        }

        // Remove
        elseif( isset($args['-r']) ) {
            $servers = $this->loadStoredServerInfo();
            if( isset($servers[$args['-r']]) ) {
                unset($servers[$args['-r']]);
                file_put_contents($this->infoFile, serialize($servers));
                \cli\line('Server removed (add flag -l to list servers)');
            }
        }

        // List servers
        elseif( in_array( '-l', $flags) ) {
            $servers = $this->loadStoredServerInfo();
            $this->listServers($servers);
        }

        // Make default
        elseif( isset($args['-d'])) {
            $servers = $this->loadStoredServerInfo();
            if( empty($servers[$args['-d']]) ) {
                \cli\line('Server does not exist....');
                $this->listServers($servers);
            } else {
                $servers['__default'] = $args['-d'];
                file_put_contents($this->infoFile, serialize($servers));
                \cli\line('%gServer "'.$args['-d'].'" set as default %n');
            }
        }

        // Add
        else {

            \cli\line('Add remote server');
            \cli\line('--------------------');

            $name = \cli\prompt('Server name (any name of your choice)');
            $address = rtrim(\cli\prompt('Server address'), '/').'/';
            $user = \cli\prompt('Admin e-mail');
            $pass = Utils::promptPassword('Admin password: ');
            $secret = trim(Utils::promptAllowingEmpty('Secret (leave empty if not used)'));

            try {

                // Try to request server

                $auth = $user.':'.$pass;
                if( $secret ) {
                    $auth = 'RC4 '. base64_encode(RC4Cipher::encrypt($secret, $auth));
                } else {
                    $auth = 'Basic '. base64_encode($auth);
                }

                $client = new Client($address);
                $client->setAuthString($auth);
                $user = $client->me(); // just to check that auth is correct
                if( !$user ) {
                    throw new \Exception('Could not authenticate');
                }

                $version = $client->serverVersion();

                $this->addServer($name, $address, $auth);

                \cli\line('%gSuccessfully added server "'.$name.'" (v'.$version.')%n');
                \cli\line('... add flag -l to list all added servers');

            } catch(\Exception $e) {
                \cli\err('Failed adding server with message "'.$e->getMessage().'"');
            }
        }
    }

    /**
     * @param string $name
     * @param string $address
     * @param string $auth
     */
    public function addServer($name, $address, $auth)
    {
        $servers = $this->loadStoredServerInfo();
        $servers[$name] = array('address' => $address, 'auth'=>$auth);
        if( count($servers) == 1 )
            $servers['__default'] = $name;

        file_put_contents($this->infoFile, serialize($servers));
    }

    /**
     * @return array|mixed
     */
    public function loadStoredServerInfo()
    {
        if( stream_resolve_include_path($this->infoFile) !== false ) {
            return unserialize( file_get_contents($this->infoFile) );
        } else {
            return array();
        }
    }

    /**
     * Load client either specified in given args or default client
     * @param array $args
     * @return \Rocker\REST\ClientInterface
     */
    public static function loadClient($args)
    {
        $self = new self();
        $serverList = $self->loadStoredServerInfo();
        if( empty($args['-s']) ) {
            if( empty($serverList['__default']) || empty($serverList[$serverList['__default']]) ) {
                \cli\line('%rNo server given as argument nor set as default%n');
                return null;
            }
            $serverName = $serverList['__default'];
        } else {
            $serverName = $args['-s'];
        }

        if( empty($serverList[$serverName]) ) {
            \cli\line('%rServer "'.$serverName.'" does not exist%n');
            return null;
        }

        $client = new Client($serverList[$serverName]['address']);
        $client->setAuthString($serverList[$serverName]['auth']);
        return $client;
    }

}