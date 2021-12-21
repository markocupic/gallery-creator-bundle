<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AddBackendAssetsSubscriber implements EventSubscriberInterface
{
    protected $scopeMatcher;

    public function __construct(ScopeMatcher $scopeMatcher)
    {
        $this->scopeMatcher = $scopeMatcher;
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($request && $this->scopeMatcher->isBackendRequest($request)) {
            // Check tables script
            if (\count($_GET) <= 2 && 'gallery_creator' === $request->query->get('do') && 'reviseDatabase' !== $request->query->get('key')) {
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_check_tables.js';
            }

            // Revise table script
            if ('gallery_creator' === $request->query->get('do') && 'reviseDatabase' === $request->query->get('key')) {
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_revise_tables.js';
            }

            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be.js';
            $GLOBALS['TL_CSS'][] = 'bundles/markocupicgallerycreator/css/gallery_creator_be.css';
        }
    }
}
