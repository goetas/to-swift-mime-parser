<?php

namespace Goetas\Mail\ToSwiftMailParser\Exception;

class EndOfPartReachedException extends \Exception
{
    protected $data;

    public function __construct(string $data)
    {
        $this->data = $data;
        parent::__construct();
    }

    public function getData(): string
    {
        return $this->data;
    }
}
