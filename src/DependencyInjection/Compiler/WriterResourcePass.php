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

namespace Markocupic\ExportTable\DependencyInjection\Compiler;

use Markocupic\ExportTable\Dca\TlExportTable;
use Markocupic\ExportTable\Export\ExportTable;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class WriterResourcePass implements CompilerPassInterface
{
    /**
     * @throws ServiceNotFoundException
     */
    public function process(ContainerBuilder $container): void
    {
        $definition1 = $container->findDefinition(ExportTable::class);
        $definition2 = $container->findDefinition(TlExportTable::class);

        // Find all service IDS with the "markocupic_export_table.writer" tag.
        $taggedServices = $container->findTaggedServiceIds('markocupic_export_table.writer');

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                if (!isset($attributes['alias'])) {
                    throw new InvalidArgumentException(sprintf('Missing tag information "alias" on markocupic_export_table.writer tagged service "%s".', $id));
                }

                // Inject writers
                $definition1->addMethodCall(
                    'addWriter',
                    [
                        new Reference($id),
                        $attributes['alias'],
                    ]
                );

                // Inject export types (csv, xml, etc.)
                $definition2->addMethodCall(
                    'addWriterAlias',
                    [
                        $attributes['alias'],
                    ]
                );
            }
        }
    }
}
