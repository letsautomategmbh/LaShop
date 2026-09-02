<?php

namespace Modules\LaStore\Services\Transport;

interface Transport
{
    /**
     * @param string $path
     * @param array  $query
     * @param array  $headers
     *
     * @return array
     */
    public function get($path, array $query = [], array $headers = []);

    /**
     * @param string $path
     * @param array  $body
     * @param array  $headers
     *
     * @return array
     */
    public function post($path, array $body = [], array $headers = []);
}
