<?php

/**
 * 连接类，对socket的一层包装
 */
class TcpConnection
{
    // socket
    protected $_socket = null;

    // 当前连接属于哪个worker
    protected $_worker = null;

    // 构造函数
    public function __construct($socket, $worker)
    {
        $this->_socket = $socket;
        $this->_worker = $worker;
    }

    // 向对方发送消息
    public function send($buffer) 
    {
        if (feof($this->_socket)) return;
        return fwrite($this->_socket, $buffer);
    }

    // 关闭连接
    public function close()
    {
        // 通知worker不再监听该socket可读事件
        $this->_worker->removeListener($this->_socket);
        return fclose($this->_socket);
    }
}
