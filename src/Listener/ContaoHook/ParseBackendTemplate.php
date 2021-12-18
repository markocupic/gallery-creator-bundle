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

namespace Markocupic\GalleryCreatorBundle\Listener\ContaoHook;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Hook(ParseBackendTemplate::HOOK)
 */
class ParseBackendTemplate
{
    public const HOOK = 'parseBackendTemplate';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * Adapt the form encoding for the image uploader in the contao backend.
     */
    public function __invoke(string $strContent): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $this->scopeMatcher->isBackendRequest($request)){

            if('tl_gallery_creator_albums' === $request->query->get('table') && 'fileupload' === $request->query->get('mode')) {
                // Form encoding
                $strContent = str_replace('application/x-www-form-urlencoded', 'multipart/form-data', $strContent);
            }
        }


        return $strContent;
    }
}
