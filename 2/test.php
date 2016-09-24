<?php
ini_set('display_errors', 'on');
error_reporting(E_ALL);
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/TcpConnection.php';

// 初始化容器
$server =  new Worker('tcp://0.0.0.0:1215');

// 客户端连接上来时
$server->onConnect = function($conn){
    $conn->send("input your name : ");
};

// 客户端发来消息时
$server->onMessage = function($conn, $msg) use ($server){
    if(!isset($conn->name)){
        $conn->name = trim($msg);
        broadcast("{$conn->name} come");
        return;
    }
    broadcast("{$conn->name} said: $msg");
};

// 客户端连接关闭时
$server->onClose = function($conn){
    broadcast("{$conn->name} logout");
};

// 广播消息
function broadcast($msg)
{
    global $server;
    foreach ($server->connections as $connection) {
        $connection->send($msg."\r\n");
    }
}

// 运行容器
$server->run();
