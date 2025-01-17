<?php
namespace Imefisto\PsrSwoole;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;
use Dflydev\FigCookies\SetCookies;

class ResponseMerger
{
    const FSTAT_MODE_S_IFIFO = 0010000;
    const BUFFER_SIZE = 8192;
    
    public function toSwoole(ResponseInterface $psrResponse, Response $swooleResponse): Response
    {
        $swooleResponse->status($psrResponse->getStatusCode());
        $this->copyHeaders($psrResponse, $swooleResponse);
        $this->copyBody($psrResponse, $swooleResponse);

        return $swooleResponse;
    }

    private function copyHeaders($psrResponse, $swooleResponse)
    {
        if (empty($psrResponse->getHeaders())) {
            return;
        }

        $this->setCookies($swooleResponse, $psrResponse);

        $psrResponse = $psrResponse->withoutHeader('Set-Cookie');
        
        foreach ($psrResponse->getHeaders() as $key => $headerArray) {
            $swooleResponse->header($key, implode('; ', $headerArray));
        }
    }

    private function setCookies($swooleResponse, $psrResponse)
    {
        if (!$psrResponse->hasHeader('Set-Cookie')) {
            return;
        }

        $setCookies = SetCookies::fromSetCookieStrings($psrResponse->getHeader('Set-Cookie'));
        foreach ($setCookies->getAll() as $setCookie) {
            $swooleResponse->cookie(
                $setCookie->getName(),
                $setCookie->getValue(),
                $setCookie->getExpires(),
                $setCookie->getPath(),
                $setCookie->getDomain(),
                $setCookie->getSecure(),
                $setCookie->getHttpOnly(),
                $this->getSameSiteModifier($setCookie)
            );
        }
    }

    private function getSameSiteModifier($setCookie)
    {
        if ($sameSite = $setCookie->getSameSite()) {
            return explode('=', strtolower($sameSite->asString()))[1];
        }

        return null;
    }

    private function copyBody($psrResponse, $swooleResponse)
    {
        if ($psrResponse->getBody()->getSize() == 0) {
            $this->copyBodyIfIsAPipe($psrResponse, $swooleResponse);
            return;
        }

        if ($psrResponse->getBody()->isSeekable()) {
            $psrResponse->getBody()->rewind();
        }

        $swooleResponse->write($psrResponse->getBody()->getContents());
    }

    private function copyBodyIfIsAPipe($psrResponse, $swooleResponse)
    {
        $resource = $psrResponse->getBody()->detach();

        if (!is_resource($resource)) {
            return;
        }

        if ($this->isPipe($resource)) {
            while (!feof($resource)) {
                $buff = fread($resource, self::BUFFER_SIZE);
                !empty($buff) && $swooleResponse->write($buff);
            }
            pclose($resource);
        }
    }

    private function isPipe($resource)
    {
        $stat = fstat($resource);
        return ($stat['mode'] & self::FSTAT_MODE_S_IFIFO) === self::FSTAT_MODE_S_IFIFO;
    }
}
