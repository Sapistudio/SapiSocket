<?php
namespace SapiStudio\DnsRecords;

/**
 * SocketConnection
 * 
 * @package 
 * @copyright 2017
 * @version $Id$
 * @access public
 */
 
class SocketConnection
{
    private $socketResource     = null;
    protected $hostname         = null;
    protected $socketOptions    = ['connectionTimeout' => 30, 'streamTimeout' => 30];
    protected $socketProtocol   = 'tcp';
    protected $socketPort       = null;

    /**
     * SocketConnection::open()
     */
    public static function open($hostname, $port = null)
    {
        return (new static())->setHostName($hostname)->setSocketPort($port)->connect();
    }
    
    /**
     * SocketConnection::__destruct()
     */
    public function __destruct(){
        $this->close();
    }
    
    /**
     * SocketConnection::setHostName()
     */
    public function setHostName($hostname)
    {
        $this->hostname = $hostname;
        return $this;
    }
    
    /**
     * SocketConnection::setProtocol()
     */
    public function setProtocol($protocol)
    {
        $this->socketProtocol = $protocol;
        return $this;
    }

    /**
     * SocketConnection::getHostName()
     */
    public function getHostName()
    {
        return $this->hostname;
    }

    /**
     * SocketConnection::getPort()
     */
    public function getPort()
    {
        return $this->socketPort;
    }

    /**
     * SocketConnection::setSocketPort()
     */
    public function setSocketPort($port)
    {
        $this->socketPort = $port;
        return $this;
    }

    /**
     * SocketConnection::getSocketStream()
     */
    protected function getSocketStream()
    {
        if (!is_resource($this->socketResource)) {
            $this->connect();
            return $this->socketResource;
        }
        $meta = stream_get_meta_data($this->socketResource);
        if (($meta['timed_out'] || $meta['eof']))
            $this->connect();
        return $this->socketResource;
    }

    /**
     * SocketConnection::connect()
     */
    protected function connect()
    {
        $host = "{$this->socketProtocol}://{$this->hostname}";
        if ($this->socketPort) {
            $host .= ":{$this->socketPort}";
        }
        $socketStream = @stream_socket_client($host, $errorNumber, $errorMessage, $this->socketOptions['connectionTimeout']);
        if (!is_resource($socketStream)) {
            throw new \Exception("{$errorNumber}: Error connecting to socket: [{$errorMessage}]");
        }
        stream_set_timeout($socketStream, $this->socketOptions['streamTimeout']);
        stream_set_blocking($socketStream, true);
        $this->socketResource = $socketStream;
        return $this;
    }

    /**
     * SocketConnection::sendMessage()
     */
    public function sendMessage($message)
    {
        $message = sprintf("%s\r\n", $message);
        fwrite($this->getSocketStream(), $message, strlen($message));
        return $this->receiveMessage();
    }

    /**
     * SocketConnection::sendMessageWithoutAnswer()
     */
    public function sendMessageWithoutAnswer($message)
    {
        fwrite($this->getSocketStream(), $message, strlen($message));
        return $this;
    }

    /**
     * SocketConnection::receiveMessage()
     */
    public function receiveMessage($length = null)
    {
        if (null === $length)
            return stream_get_contents($this->getSocketStream());
        $response = null;
        while ($chunk = fread($this->getSocketStream(), 1024)) {
            $response .= $chunk;
            if (substr($chunk, -1) === "\n") {
                break;
            }
        }
        return $response;
    }

    /**
     * SocketConnection::close()
     */
    public function close()
    {
        if (is_resource($this->socketResource))
            fclose($this->socketResource);
        return $this;
    }
}
