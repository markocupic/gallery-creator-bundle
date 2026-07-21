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

namespace Markocupic\GalleryCreatorBundle\Revise;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ReviseAlbumDatabase
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private Filesystem $filesystem,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private string $projectDir,
        private string $galleryCreatorUploadPath,
    ) {
    }

    /**
     * @throws DoctrineDBALException
     */
    public function run(GalleryCreatorAlbumsModel $albumModel, bool $blnCleanDb = false): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $session = $request->getSession();
        $session->set('gc_error', []);

        // Create the upload directory if it doesn't exist.
        $this->filesystem->mkdir(Path::makeAbsolute($this->galleryCreatorUploadPath, $this->projectDir));

        // Check for valid pid
        if ((int) $albumModel->pid > 0) {
            $parentAlbum = $albumModel->getRelated('pid');

            if (null === $parentAlbum) {
                $albumModel->pid = null;
                $albumModel->save();
            }
        }

        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Try to identify entries with no uuid via path
        $picturesModel = $this->framework->getAdapter(GalleryCreatorPicturesModel::class)->findByPid($albumModel->id);

        if (null !== $picturesModel) {
            while ($picturesModel->next()) {
                // Get parent album
                $filesModel = $this->framework->getAdapter(FilesModel::class)->findByUuid($picturesModel->uuid);

                if (null === $filesModel) {
                    $errors = $session->get('gc_error');

                    if ($blnCleanDb) {
                        $errors[] = \sprintf('Deleted data record with ID %s in Album "%s".', $picturesModel->id, $albumModel->name);
                        $picturesModel->delete();
                    } else {
                        // Show error-message
                        $errors[] = $this->translator->trans('ERR.linkToNotExistingFile', [$picturesModel->id, 'UUID: '.$stringUtilAdapter->binToUuid($picturesModel->uuid), $albumModel->alias], 'contao_default');
                    }

                    $session->set('gc_error', $errors);
                } elseif (!$this->filesystem->exists(Path::makeAbsolute($filesModel->path, $this->projectDir))) {
                    $errors = $session->get('gc_error');

                    // If there is a data record for the file, but the file doesn't exist in the filesystem anymore.
                    if ($blnCleanDb) {
                        $errors[] = \sprintf('Deleted data record with ID %s in Album "%s".', $picturesModel->id, $albumModel->name);
                        $picturesModel->delete();
                    } else {
                        $errors[] = $this->translator->trans('ERR.linkToNotExistingFile', [$picturesModel->id, $filesModel->path, $albumModel->alias], 'contao_default');
                    }

                    $session->set('gc_error', $errors);
                }
            }
        }

        /**
         * Ensures that there are no orphaned AlbumId's in the gcAlbumSelection field in tl_content.
         * Checks whether the albums defined in the content element still exist.
         * If not, these are removed from the array.
         */
        $contents = $this->connection->fetchAllAssociative('SELECT * FROM tl_content WHERE type = ?', ['gallery_creator']);

        foreach ($contents as $content) {
            $newIds = [];
            $albumIds = $this->framework->getAdapter(StringUtil::class)->deserialize($content['gcAlbumSelection'], true);

            foreach ($albumIds as $albumId) {
                if (0 === (int) $albumId) {
                    // "0" means: "show all album items"
                    continue;
                }

                $id = $this->connection->fetchOne('SELECT id FROM tl_gallery_creator_albums WHERE id = ?', [$albumId]);

                if (false !== $id) {
                    $newIds[] = (int) $id;
                }
            }

            $this->connection->update(
                'tl_content',
                ['tl_content.gcAlbumSelection' => serialize($newIds)],
                ['tl_content.id' => $content['id']],
            );
        }
    }
}
