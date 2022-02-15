<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use League\CommonMark\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;


class MarkdownUtil
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
    }

    public function parse(string $strMarkdown): string
    {

        $request = $this->requestStack->getCurrentRequest();

        /** @var Config $config */
        $config = $this->framework->getAdapter(Config::class);

        /** @var Input $input */
        $input =  $this->framework->getAdapter(Input::class);

        $html = $this->createConverter($request)->convertToHtml($strMarkdown);
        $html = $input->stripTags($html, $config->get('allowedTags'), $config->get('allowedAttributes'));

        return $html;
    }


    private function createConverter(Request $request): MarkdownConverter
    {
        $environment = Environment::createCommonMarkEnvironment();

        // Support GitHub flavoured Markdown (using the individual extensions because we don't want the
        // DisallowedRawHtmlExtension which is included by default)
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());

        // Automatically mark external links as such if we have a request
        $environment->addExtension(new ExternalLinkExtension());

        $environment->mergeConfig([
            'external_link' => [
                'internal_hosts' => $request->getHost(),
                'open_in_new_window' => true,
                'html_class' => 'external-link',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        return new MarkdownConverter($environment);
    }

}
