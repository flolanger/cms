<?php

namespace Statamic\StaticCaching\Replacers;

use Illuminate\Http\Response;
use Statamic\StaticCaching\Cacher;
use Statamic\StaticCaching\Cachers\FileCacher;
use Statamic\StaticCaching\NoCache\CacheSession;
use Statamic\StaticCaching\Replacer;

class NoCacheReplacer implements Replacer
{
    const PATTERN = '/<span class="nocache" data-nocache="([\w\d]+)">NOCACHE_PLACEHOLDER<\/span>/';

    private $session;

    public function __construct(CacheSession $session)
    {
        $this->session = $session;
    }

    public function prepareResponseToCache(Response $responseToBeCached, Response $initialResponse)
    {
        $this->replaceInResponse($initialResponse);

        $this->modifyFullMeasureResponse($responseToBeCached);
    }

    public function replaceInCachedResponse(Response $response)
    {
        $this->replaceInResponse($response);
    }

    private function replaceInResponse(Response $response)
    {
        if (! $content = $response->getContent()) {
            return;
        }

        if (preg_match(self::PATTERN, $content)) {
            $this->session->restore();
        }

        $response->setContent($this->replace($content));
    }

    public function replace(string $content)
    {
        while (preg_match(self::PATTERN, $content)) {
            $content = $this->performReplacement($content);
        }

        return $content;
    }

    private function performReplacement(string $content)
    {
        return preg_replace_callback(self::PATTERN, function ($matches) {
            if (! $section = $matches[1] ?? null) {
                return '';
            }

            return $this->session->getView($section)->render();
        }, $content);
    }

    private function modifyFullMeasureResponse(Response $response)
    {
        $cacher = app(Cacher::class);

        if (! $cacher instanceof FileCacher) {
            return;
        }

        $js = $cacher->getNocacheJs();

        $contents = str_replace('</body>', '<script type="text/javascript">'.$js.'</script></body>', $response->getContent());

        $contents = str_replace('NOCACHE_PLACEHOLDER', $cacher->getNocachePlaceholder(), $contents);

        $response->setContent($contents);
    }
}