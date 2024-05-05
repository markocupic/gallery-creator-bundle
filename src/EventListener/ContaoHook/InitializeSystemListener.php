<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\EventListener\ContaoHook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Input;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @todo Handle this issue in a more proper way
 */
#[AsHook(InitializeSystemListener::HOOK, priority: 100)]
class InitializeSystemListener
{
    public const HOOK = 'initializeSystem';

    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function __invoke(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Show news ce_element in the news-module only
        if ($request && $this->scopeMatcher->isBackendRequest($request) && 'news' === Input::get('do')) {
            unset($GLOBALS['TL_CTE']['gallery_creator_elements']['gallery_creator']);
        }

        if ($request && $this->scopeMatcher->isBackendRequest($request) && 'news' !== Input::get('do')) {
            unset($GLOBALS['TL_CTE']['gallery_creator_elements']['gallery_creator_news']);
        }
    }
}
