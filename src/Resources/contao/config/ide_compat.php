<?php
/**
 * Gallery Creator Bundle
 * Provide methods for using the gallery_creator extension
 * @copyright  Marko Cupic 2019
 * @license MIT
 * @author     Marko Cupic, Oberkirch, Switzerland ->  mailto: m.cupic@gmx.ch
 * @package    Gallery Creator Bundle
 */

// This file is not used in Contao. Its only purpose is to make PHP IDEs like
// Eclipse, Zend Studio or PHPStorm realize the class origins, since the dynamic
// class aliasing we are using is a bit too complex for them to understand.
namespace  {
	class GalleryCreatorAlbumsModel extends \Contao\GalleryCreatorAlbumsModel {}
	class GalleryCreatorPicturesModel extends \Contao\GalleryCreatorPicturesModel {}
	class ContentGalleryCreator extends \Markocupic\GalleryCreatorBundle\ContentGalleryCreator {}
	class ContentGalleryCreatorNews extends \Markocupic\GalleryCreatorBundle\ContentGalleryCreatorNews {}
	class GcHelpers extends \Markocupic\GalleryCreatorBundle\GcHelpers {}
	class InstallConfig extends \Markocupic\GalleryCreatorBundle\InstallConfig {}
}
