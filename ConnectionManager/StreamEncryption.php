<?php

namespace ConnectionManager;

use React\Promise\ResolverInterface;
use React\Promise\Deferred;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;
use \UnexpectedValueException;
use \InvalidArgumentException;

class StreamEncryption
{
    private $loop;

    private $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function setMethod($method)
    {
//         if (!in_array($method, array(), true)) {
//             throw new InvalidArgumentException('Invalid encryption method given');
//         }
        $this->method = $method;
    }

    public function enable(Stream $stream)
    {
        return $this->toggle($stream, true);
    }

    public function disable(Stream $stream)
    {
        return $this->toggle($stream, false);
    }

    // heavily inspired by HttpClient\SecureConnectionManager
    public function toggle(Stream $stream, $toggle)
    {
        // pause actual stream instance to continue operation on raw stream socket
        $stream->pause();

        // TODO: add write() event to make sure we're not sending any excessive data

        $deferred = new Deferred();

        // get actual stream socket from stream instance
        $socket = $stream->stream;

        $that = $this;
        $toggleCrypto = function () use ($that, $socket, $deferred, $toggle) {
            $that->toggleCrypto($socket, $deferred, $toggle);
        };

        $this->loop->addWriteStream($socket, $toggleCrypto);
        $this->loop->addReadStream($socket, $toggleCrypto);
        $toggleCrypto();

        return $deferred->then(function () use ($stream) {
            $stream->resume();
            return $stream;
        }, function($error) use ($stream) {
            $stream->resume();
            throw $error;
        });
    }



    public function toggleCrypto($socket, ResolverInterface $resolver, $toggle)
    {
        $error = 'unknown error';
        set_error_handler(function ($errno, $errstr) use (&$error) {
            $error = str_replace(array("\r","\n"),' ',$errstr);
        });

        $result = stream_socket_enable_crypto($socket, $toggle, $this->method);

        restore_error_handler();

        if (true === $result) {
            $this->loop->removeWriteStream($socket);
            $this->loop->removeReadStream($socket);

            $resolver->resolve();
        } else if (false === $result) {
            $this->loop->removeWriteStream($socket);
            $this->loop->removeReadStream($socket);

            $resolver->reject(new UnexpectedValueException('Unable to initiate SSL/TLS handshake: "'.$error.'"'));
        } else {
            // need more data, will retry
        }
    }
}
