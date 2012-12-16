<?php

namespace ConnectionManager;

// temporarily extend HttpClient\ConnectionmanagerInterface to provide a compatible implementation
// TODO: remove me and make sure that at some point HttpClient will only use this interface
interface ConnectionManagerInterface extends \React\HttpClient\ConnectionManagerInterface
{
    // no need to re-define interface from HttpClient\ConnectionManagerInterface
    public function getConnection($host, $port);
}
