<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AddBackendAssetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Packages $packages,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request && $this->scopeMatcher->isBackendRequest($request)) {
            // The Stimulus application is loaded across the whole backend. Each
            // controller attaches its data-controller attribute to #main by
            // itself (static afterLoad) and gates its own behaviour, so no
            // per-page bootstrap scripts are required.
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('stimulus_backend.js', 'markocupic_gallery_creator');
            $GLOBALS['TL_CSS'][] = $this->packages->getUrl('css/backend.css', 'markocupic_gallery_creator');
        }
    }
}
