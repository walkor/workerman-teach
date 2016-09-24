<?php
/**
 * 单进程IO复用select
 * 同时处理多个连接
 */
class Worker 
{
    // 连接事件回调
    public $onConnect  = null;
    // 消息事件回调
    public $onMessage  = null;
    // 连接关闭事件回调
    public $onClose    = null;
    // 监听端口的socket
    protected $socket     = null;
    // 所有socket，包括监听的socket
    protected $allSockets = array();

    public $connections = array();

    // 构造函数
    function __construct($address)
    {
        // 创建监听socket
        $this->socket = stream_socket_server($address, $errno, $errstr);
        echo "listen $address\r\n";
        // 设置为非阻塞
        stream_set_blocking($this->socket, 0);
        // 将监听socket放入allSockets
        $this->allSockets[(int)$this->socket] = $this->socket;
    }

    // 运行
    public function run(){
        while ( 1 ) {
            // 这里不监听socket可写事件和带外数据可读事件
            $write = $except = null;
            // 监听所有socket可读事件，包括客户端socket和监听端口的socket
            $read = $this->allSockets;
            // 整个程序阻塞在这里，等待$read里面的socket可读，这里$read是个引用参数
            stream_select($read, $write, $except, 60);
            // $read被重新赋值，遍历所有状态为可读的socket
            foreach ( $read as $index => $socket ) {
                // 如果是监听socket可读，说明有新连接
                if ( $socket === $this->socket ) {
                    // 通过stream_socket_accept获取新连接
                    $new_conn_socket = stream_socket_accept($this->socket);
                    if (!$new_conn_socket) continue;
                    $connection = new TcpConnection($new_conn_socket, $this);
                    $this->connections[(int)$new_conn_socket] = $connection;
                    // 如果有onConnect事件回调，则尝试触发
                    if($this->onConnect) {
                        call_user_func($this->onConnect, $connection);
                    }
                    // 将新的客户端连接socket放入allSockets，以便stream_select监听其可读事件
                    $this->allSockets[(int)$new_conn_socket] = $new_conn_socket;
                // 是客户端连接可读，说有对应连接的客户端有数据发来
                } else {
                    // 读数据
                    $buffer = fread($socket, 65535);
                    // 数据为空，代表连接已经断开
                    if ( $buffer === '' || $buffer === false ) {
                        // 尝试触发onClose回调
                        if ( $this->onClose ){
                            call_user_func($this->onClose, $this->connections[(int)$socket]);
                        }
                        fclose($socket);
                        // 从allSockets里删除对应的连接，不在监听这个socket可读事件
                        unset($this->allSockets[(int)$socket], $this->connections[(int)$socket]);
                        continue; 
                    } 
                    // 尝试触发onMesage回调
                    call_user_func($this->onMessage, $this->connections[(int)$socket], $buffer);
                }
            }
        } //end while
    }

    public function removeListener($socket)
    {
        unset($this->allSockets[(int)$socket]);
    }
}
