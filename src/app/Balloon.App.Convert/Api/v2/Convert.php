<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Convert\Converter;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Convert extends Controller
{
    /**
     * Converter.
     *
     * @var Converter
     */
    protected $converter;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     *
     * @param Converter $converter
     * @param Server    $server
     */
    public function __construct(Converter $converter, Server $server)
    {
        $this->fs = $server->getFilesystem();
        $this->converter = $converter;
    }

    /**
     * @api {get} /api/v2/files/:id/convert/supported-formats Get supported formats
     * @apiVersion 2.0.0
     * @apiName getSupportedFormats
     * @apiGroup App\Convert
     * @apiPermission none
     * @apiDescription Get supported file formats to convert to (formats do vary between files)
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/files/convert/supported-formats?id=544627ed3c58891f058b4686"
     *
     * @apiSuccess {string[]} - List of supported formats
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  "png",
     *  "jpg",
     *  "tiff"
     * ]
     *
     * @param string $id
     * @param string $p
     */
    public function getSupportedFormats(?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $result = $this->converter->getSupportedFormats($file);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v2/files/:id/convert/slaves Get slaves
     * @apiVersion 2.0.0
     * @apiName getSlaves
     * @apiGroup App\Convert
     * @apiPermission none
     * @apiDescription Get existing conversion slaves
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XGET "https://SERVER/api/v2/files/convert/slaves?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * [
     * ]
     *
     * @param string $id
     * @param string $p
     */
    public function getSlaves(?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $body = [];

        foreach ($this->converter->getSlaves($file) as $slave) {
            $element = [
                'id' => (string) $slave['_id'],
                'master' => (string) $slave['master'],
                'format' => $slave['format'],
            ];

            if (isset($slave['slave'])) {
                $element['slave'] = (string) $slave['slave'];
            }

            $body[] = $element;
        }

        return (new Response())->setCode(200)->setBody($body);
    }

    /**
     * @api {post} /api/v2/files/:id/convert/slaves Add new slave
     * @apiVersion 2.0.0
     * @apiName postSlaves
     * @apiGroup App\Convert
     * @apiPermission none
     * @apiDescription Add new conversion slave
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v2/files/convert/slave?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "944627ed3c58891f058b468e",
     *      "master": "944627ed3c58891f058b4686",
     *      "format": "png",
     * }
     *
     * @param string $id
     * @param string $p
     * @param string $format
     */
    public function postSlaves(string $format, ?string $id = null, ?string $p = null): Response
    {
        $file = $this->fs->getNode($id, $p, File::class);
        $id = $this->converter->addSlave($file, $format);

        return (new Response())->setCode(200)->setBody([
            'id' => (string) $id,
            'master' => (string) $file->getId(),
            'format' => $format,
        ]);
    }

    /**
     * @api {delete} /api/v2/files/:id/convert/slaves Delete slave
     * @apiVersion 2.0.0
     * @apiName deleteSlaves
     * @apiGroup App\Convert
     * @apiPermission none
     * @apiDescription Delete conversion slave
     * @apiUse _getNode
     *
     * @apiExample (cURL) exmaple:
     * curl -XDELETE "https://SERVER/api/v2/files/convert/slave?id=544627ed3c58891f058b4686"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $id
     * @param string $p
     * @param string $slave
     * @param bool   $node
     */
    public function deleteSlaves(ObjectId $id, bool $node = false): Response
    {
        $this->converter->deleteSlave($id, $node);

        return (new Response())->setCode(204);
    }
}
