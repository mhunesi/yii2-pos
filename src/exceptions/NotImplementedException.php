<?php


namespace mhunesi\pos\exceptions;

/**
 * Class NotIplementedException
 */
class NotImplementedException extends \BadMethodCallException
{
    /**
     * @inheritDoc
     */
    public function __construct($message = 'Not implemented!', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}