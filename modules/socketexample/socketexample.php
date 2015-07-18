<?php
class socketexample_mod extends module
{

    public $title = "Socketexample";
    public $author = "g2x3k";
    public $version = "1.0";
    private $delay = 0;

    public function init()
    {
        // to send to this socket see gist: https://gist.github.com/g2x3k/574ae2f43708e5433f6f
        // socket.listen
        $this->error = false;
        $this->port = 3666;
        $this->annpass = "somepassword";
	// socket init
        $conn = new connection(null, $this->port, 0);
        $conn->setSocketClass($this->socketClass);
        $conn->setIrcClass($this->ircClass);
        $conn->setCallbackClass($this);
        $conn->setTimerClass($this->timerClass);
        $conn->setTransTimeout(5);

        $conn->init();

        if ($conn->getError()) {
            $this->error == true;
            return;
        }
	
        $this->siteListener = $conn;
	.
    }

    // socket listen
    public function onRead($conn)
    {
        $connInt = $conn->getSockInt();
        $q = $this->socketClass->getQueueLine($connInt);
        echo " 1 Reading Data: $q \r\n";
        $request = explode(" ", $q);
        // new incomming syntax: pass action target content
        // pass: annpass
        // action: msg/raw
        // target: #channel/nick or null
        $annpass = trim($request[0]);
        $action = trim($request[1]);
        $target = trim($request[2]);
        $content = trim(str_replace(array($annpass, $action, $target), "", $q));

        if ($annpass == $this->annpass) {
            if ($action == "msg")
                $this->ircClass->privMsg($target, "$content");
            elseif ($action == "raw")
                $this->ircClass->sendRaw($content);
        }
       
    }

    public function onAccept($oldConn, $newConn)
    {
        /* dummy */
        $this->onConnect($newConn);
    }
    public function onConnect($conn)
    {
        /* dummy */
    }
    public function onDead($conn)
    {
        $conn->disconnect();
    }
    public function onTransferTimeout($conn)
    {
        /* dummy */
    }

}