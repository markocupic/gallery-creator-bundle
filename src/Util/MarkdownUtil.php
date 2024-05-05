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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\CommonMarkExtension;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Input;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MarkdownUtil
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly InsertTagParser $insertTagParser,
    ) {
    }

    public function parse(string $markdown): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $config = $this->framework->getAdapter(Config::class);
        $input = $this->framework->getAdapter(Input::class);
        $html = $this->createConverter($request)->convert($markdown);

        return $input->stripTags($html, $config->get('allowedTags'), $config->get('allowedAttributes'));
    }

    /**
     * Hint: This is protected on purpose, so you can override it for your app specific requirements.
     * If you want to provide an extension with additional logic, consider providing your own special
     * content element for that.
     */
    protected function createConverter(Request $request): ConverterInterface
    {
        $environment = new Environment([
            'external_link' => [
                'internal_hosts' => $request->getHost(),
                'open_in_new_window' => true,
                'html_class' => 'external-link',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkExtension($this->insertTagParser));
        $environment->addExtension(new CommonMarkCoreExtension());

        // Support GitHub flavoured Markdown (using the individual extensions because we don't want the
        // DisallowedRawHtmlExtension which is included by default)
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());

        // Automatically mark external links as such if we have a request
        $environment->addExtension(new ExternalLinkExtension());

        return new MarkdownConverter($environment);
    }
}
