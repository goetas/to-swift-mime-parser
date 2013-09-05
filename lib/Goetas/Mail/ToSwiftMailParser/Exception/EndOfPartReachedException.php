<?php

namespace Goetas\Mail\ToSwiftMailParser\Exception;

class EndOfPartReachedException extends \Exception {
    protected $data = array ();
    public function __construct($data) {
        $this->data = $data;
        parent::__construct ();
    }
    public function getData() {
        return $this->data;
    }
}