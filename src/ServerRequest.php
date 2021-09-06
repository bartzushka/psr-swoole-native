<?php

namespace Imefisto\PsrSwoole;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Swoole\Http\Request as SwooleRequest;

class ServerRequest extends Request implements ServerRequestInterface
{
    public array $attributes = [];
    public array $serverParams;


    public function __construct(
        SwooleRequest                $swooleRequest,
        UriFactoryInterface          $uriFactory,
        StreamFactoryInterface       $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory
    )
    {
        parent::__construct($swooleRequest, $uriFactory, $streamFactory);
        $this->uploadedFileFactory = $uploadedFileFactory;
        $this->serverParams = $this->extractServerParams();
    }

    private function extractServerParams()
    {
        $res = [];
        $server = $this->swooleRequest->server;
        foreach ($server as $k => $v) {
            $res[strtoupper($k)] = $v;
        }
        return $res;
    }

    public function getServerParams()
    {
        return $this->serverParams;
    }

    public function getHeader($name)
    {
        return parent::getHeader(strtolower($name));
    }

    public function getCookieParams()
    {
        return $this->cookies ?? ($this->swooleRequest->cookie ?? []);
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams()
    {
        return $this->query ?? ($this->swooleRequest->get ?? []);
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function getUploadedFiles()
    {
        if (isset($this->files)) {
            return $this->files;
        }

        $files = [];

        foreach ($this->swooleRequest->files as $name => $fileData) {
            $files[$name] = $this->uploadedFileFactory->createUploadedFile(
                $this->streamFactory->createStreamFromFile($fileData['tmp_name']),
                $fileData['size'],
                $fileData['error'],
                $fileData['name'],
                $fileData['type']
            );
        }

        return $files;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->files = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        if (property_exists($this, 'parsedBody')) {
            return $this->parsedBody;
        }

        if (!empty($this->swooleRequest->post)) {
            return $this->swooleRequest->post;
        }

        return null;
    }

    public function withParsedBody($data)
    {
        if (!\is_object($data) && !\is_array($data) && !\is_null($data)) {
            throw new \InvalidArgumentException('Unsupported argument type');
        }

        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
