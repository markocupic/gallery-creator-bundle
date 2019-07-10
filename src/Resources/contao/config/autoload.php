<?php
/**
 * Gallery Creator Bundle
 * Provide methods for using the gallery_creator extension
 * @copyright  Marko Cupic 2019
 * @license MIT
 * @author     Marko Cupic, Oberkirch, Switzerland ->  mailto: m.cupic@gmx.ch
 * @package    Gallery Creator Bundle
 */



/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'ce_gc_news_default'   => 'vendor/markocupic/gallery-creator-bundle/src/Resources/contao/templates',
	'ce_gc_default'        => 'vendor/markocupic/gallery-creator-bundle/src/Resources/contao/templates',
	'be_gc_html5_uploader' => 'vendor/markocupic/gallery-creator-bundle/src/Resources/contao/templates',
));
