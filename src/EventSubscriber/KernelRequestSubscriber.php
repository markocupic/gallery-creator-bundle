<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(protected readonly ScopeMatcher $scopeMatcher)
    {
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if (2 === $request->query->count() && $request->query->has('ref')) {
                // check tables script
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_check_tables.js';
            }

            if ('revise_tables' === $request->query->get('mode')) {
                // revise table script
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_revise_tables.js';
            }

            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be.js';
            $GLOBALS['TL_CSS'][] = 'bundles/markocupicgallerycreator/css/gallery_creator_be.css';
        }
    }
}
