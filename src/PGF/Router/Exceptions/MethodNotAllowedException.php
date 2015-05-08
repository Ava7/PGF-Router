<?php


namespace PGF\Router\Exceptions;


class MethodNotAllowedException extends \Exception{
    public function __construct($message = "")
    {
        parent::__construct($message, 0, null);
    }
}