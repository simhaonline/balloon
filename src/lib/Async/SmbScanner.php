<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Adapter\Blackhole;
use Balloon\Filesystem\Storage\Adapter\Smb;
use Balloon\Server;
use Balloon\Server\User;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\INotifyHandler;
use Icewind\SMB\IShare;
use MongoDB\BSON\UTCDateTime;
use Normalizer;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class SmbScanner extends AbstractJob
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $dummy = new Blackhole();
        $fs = $this->server->getFilesystem();
        $mount = $collection = $fs->findNodeById($this->data['id']);
        $user = $this->server->getUserById($collection->getOwner());
        $user_fs = $user->getFilesystem();
        $smb = $collection->getStorage();
        $share = $smb->getShare();
        $action = INotifyHandler::NOTIFY_ADDED;

        if (isset($this->data['action'])) {
            $action = $this->data['action'];
        }

        $collection
            ->setFilesystem($user_fs)
            ->setStorage($dummy);

        $path = $smb->getRoot();

        if (isset($this->data['path'])) {
            $path = $this->data['path'];
        }

        if ($path === '' || $path === '.') {
            $path = DIRECTORY_SEPARATOR;
        }

        if ($path !== DIRECTORY_SEPARATOR && isset($this->data['path'])) {
            $parent_path = dirname(substr(ltrim($path, '/'), strlen(ltrim($path, '/'))));
            if ($path !== '.' && $parent_path !== '') {
                $collection = $this->getParent($collection, $share, $parent_path, $action);
                $collection->setStorage($dummy);
            }
        }

        $recursive = true;
        if (isset($this->data['recursive'])) {
            $recursive = $this->data['recursive'];
        }

        $this->recursiveIterator($collection, $mount, $share, $dummy, $user, $smb, $path, $recursive, $action);

        return true;
    }

    /**
     * Get parent node from sub path.
     */
    protected function getParent(Collection $mount, IShare $share, string $path, int $action): Collection
    {
        $parent = $mount;
        $nodes = explode(DIRECTORY_SEPARATOR, $path);
        $sub = '';
        $dummy = $mount->getStorage();

        foreach ($nodes as $child) {
            if ($child === '.') {
                continue;
            }

            try {
                $sub .= DIRECTORY_SEPARATOR.$child;
                $parent = $parent->getChild($child);
                $parent->setStorage($dummy);
            } catch (\Exception $e) {
                if ($action === INotifyHandler::NOTIFY_REMOVED) {
                    throw $e;
                }
                $this->logger->debug('child node ['.$child.'] does not exits, add folder', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);

                $node = $share->stat($sub);
                $parent = $parent->addDirectory($child, $this->getAttributes($mount, $share, $node));
            }
        }

        return $parent;
    }

    /**
     * Delete node.
     */
    protected function deleteNode(NodeInterface $node, Blackhole $dummy): bool
    {
        if ($node instanceof Collection) {
            $node->doRecursiveAction(function (NodeInterface $node) use ($dummy) {
                if ($node instanceof Collection) {
                    $node->setStorage($dummy);
                }
            });
        }

        $node->delete(true);

        return true;
    }

    /**
     * Iterate recursively through smb share.
     */
    protected function recursiveIterator(Collection $parent, Collection $mount, IShare $share, Blackhole $dummy, User $user, Smb $smb, string $path, bool $recursive, int $action, bool $root = false): void
    {
        $this->logger->debug('sync smb path ['.$path.'] in mount ['.$mount->getId().'] from operation ['.$action.'], mapped to collection path ['.$parent->getPath().']', [
            'category' => get_class($this),
            'recursive' => $recursive,
        ]);

        $system_path = $path.DIRECTORY_SEPARATOR.$smb->getSystemFolder();

        if ($path === $system_path) {
            return;
        }

        try {
            $node = $share->stat($path);
        } catch (NotFoundException $e) {
            if ($action === INotifyHandler::NOTIFY_REMOVED) {
                $node = $parent->getChild(basename($path));
                $node->getParent()->setStorage($dummy);
                $this->deleteNode($node, $dummy);
            }

            return;
        }

        if ($node->isDirectory()) {
            $parent->setStorage($dummy);

            if ($path === DIRECTORY_SEPARATOR || $path === $smb->getRoot()) {
                $child = $parent;
            } else {
                if ($parent->childExists($node->getName())) {
                    $child = $parent->getChild($node->getName());
                } else {
                    $child = $parent->addDirectory($node->getName(), $this->getAttributes($mount, $share, $node));
                }
            }

            if ($recursive === true) {
                $child->setStorage($dummy);
                $nodes = [];

                foreach ($share->dir($path) as $node) {
                    if ($node->getPath() === $system_path) {
                        continue;
                    }

                    $nodes[] = Normalizer::normalize($node->getName());
                    $child_path = ($path === DIRECTORY_SEPARATOR) ? $path.$node->getName() : $path.DIRECTORY_SEPARATOR.$node->getName();

                    try {
                        $this->recursiveIterator($child, $mount, $share, $dummy, $user, $smb, $child_path, $recursive, $action, true);
                    } catch (\Exception $e) {
                        $this->logger->error('failed sync child node ['.$child_path.'] in smb mount', [
                            'category' => get_class($this),
                            'exception' => $e,
                        ]);
                    }
                }

                foreach ($child->getChildren() as $sub_child) {
                    if (!in_array($sub_child->getName(), $nodes)) {
                        $this->deleteNode($sub_child, $dummy);
                    }
                }
            }
        } else {
            $this->syncFile($parent, $mount, $node, $action, $share, $user);
        }
    }

    /**
     * Sync file.
     */
    protected function syncFile(Collection $parent, Collection $mount, IFileInfo $node, int $action, IShare $share, User $user): bool
    {
        $this->logger->debug('update smb file meta data from ['.$node->getPath().'] in parent node ['.$parent->getId().']', [
            'category' => get_class($this),
        ]);

        $attributes = $this->getAttributes($mount, $share, $node);

        if ($parent->childExists($node->getName())) {
            $file = $parent->getChild($node->getName());
            $file->getParent()->setStorage($parent->getStorage());
            if ($this->fileUpdateRequired($file, $attributes)) {
                $this->updateFileContent($parent, $share, $node, $file, $user, $attributes);
            }

            return true;
        }

        $file = $parent->addFile($node->getName(), null, $attributes);
        $file->getParent()->setStorage($parent->getStorage());
        $this->updateFileContent($parent, $share, $node, $file, $user, $attributes);

        return true;
    }

    /**
     * Set file content.
     */
    protected function updateFileContent(Collection $parent, IShare $share, IFileInfo $node, File $file, User $user, array $attributes): bool
    {
        $storage = $parent->getStorage();
        $stream = $share->read($node->getPath());
        $session = $storage->storeTemporaryFile($stream, $user);
        $file->setContent($session, $attributes);

        fclose($stream);

        return true;
    }

    /**
     * Check if file content needs to be updated.
     */
    protected function fileUpdateRequired(File $file, array $smb_attributes): bool
    {
        $meta_attributes = $file->getAttributes();

        return $smb_attributes['size'] != $meta_attributes['size'] || $smb_attributes['changed'] != $meta_attributes['changed'];
    }

    /**
     * Prepare node attributes.
     */
    protected function getAttributes(Collection $collection, IShare $share, IFileInfo $node): array
    {
        $stats = $share->stat($node->getPath());
        $mtime = new UTCDateTime($stats->getMTime() * 1000);

        $attributes = [
            'created' => $mtime,
            'changed' => $mtime,
            'storage_reference' => $collection->getId(),
            'storage' => [
                'path' => '/'.ltrim($node->getPath(), '/'),
            ],
        ];

        if (!$node->isDirectory()) {
            $attributes['size'] = $node->getSize();
        }

        return $attributes;
    }
}
