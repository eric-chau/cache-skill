<?php

namespace Jarvis\Skill\Cache;

use Doctrine\Common\Cache\FilesystemCache;
use Jarvis\Jarvis;
use Jarvis\Skill\DependencyInjection\ContainerProvider;
use Jarvis\Skill\EventBroadcaster\JarvisEvents;
use Jarvis\Skill\EventBroadcaster\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eriic.chau@gmail.com>
 */
class HttpCacheContainerProvider extends ContainerProvider
{
    /**
     * {@inheritdoc}
     */
    public static function hydrate(Jarvis $container)
    {
        parent::hydrate($container);

        if (!isset($container['http.cache'])) {
            $container['http.cache'] = function ($jarvis) {
                $config = $jarvis->settings->get('http.cache', null);

                if (!isset($config['cache_dir'])) {
                    throw new \LogicException('Parameter `cache_dir` is missing to configure http cache.');
                }

                return new FilesystemCache($config['cache_dir']);
            };
        }

        $container['response_event_container'] = new \SplObjectStorage();
        $container->lock(['http.cache', 'response_event_container']);

        $container->addReceiver(JarvisEvents::RESPONSE_EVENT, function (ResponseEvent $event) use ($container) {
            if (Response::HTTP_OK === $event->getResponse()->getStatusCode()) {
                $event->getResponse()->setLastModified(new \DateTime());
                $container['response_event_container']->attach($event);
                $event->stopPropagation();
            }
        }, Jarvis::RECEIVER_LOW_PRIORITY);

        $container->addReceiver(JarvisEvents::TERMINATE_EVENT, function () use ($container) {
            foreach ($container['response_event_container'] as $event) {
                $container['http.cache']->save(
                    $event->getRequest()->getPathInfo(),
                    [
                        'modified' => $event->getResponse()->getLastModified()->format('Y-m-d H:i:s'),
                        'content'  => $event->getResponse()->getContent(),
                    ]
                );
            }
        });
    }
}
