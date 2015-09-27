<?php

namespace Jarvis\Skill\Cache;

use Doctrine\Common\Cache\ClearableCache;
use Jarvis\Jarvis;
use Jarvis\Skill\Cache\HttpCacheContainerProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class JarvisHttpCache extends Jarvis
{
    const CONTAINER_PROVIDER_FQCN = HttpCacheContainerProvider::class;

    private $providersFqcn;

    public function __construct(array $settings = [], ClearableCache $httpCache = null)
    {
        $this->providersFqcn = isset($settings['container_provider']) ? $settings['container_provider'] : [];
        unset($settings['container_provider']);

        if (null !== $httpCache) {
            $this['http.cache'] = $httpCache;
        }

        parent::__construct($settings);
    }

    public function analyze(Request $request = null)
    {
        $request = $request ?: $this->request;
        $response = null;

        $key = $request->getPathInfo();
        if ($this['http.cache']->contains($key)) {
            $value = $this['http.cache']->fetch($key);
            $content = $value['content'];
            $modified = \DateTime::createFromFormat('Y-m-d H:i:s', $value['modified']);
            $statusCode = Response::HTTP_OK;

            $ifModified = $this->request->headers->get('if-modified-since', null);
            if (null !== $ifModified) {
                $ifModified = \DateTime::createFromFormat('D, d M Y H:i:s T', $ifModified);
                if ($modified->getTimestamp() === $ifModified->getTimestamp()) {
                    $content = null;
                    $statusCode = Response::HTTP_NOT_MODIFIED;
                }
            }

            $response = new Response($content, $statusCode);
            $response->setLastModified($modified);
        }

        if (null === $response) {
            $this->forceHydrate();
            $response = parent::analyze($request);
        }

        return $response;
    }

    public function forceHydrate()
    {
        foreach ($this->providersFqcn as $classname) {
            $this->hydrate($classname);
        }
    }
}
