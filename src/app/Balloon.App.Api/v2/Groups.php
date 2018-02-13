<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\Exception;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\Group;
use Balloon\Server\User;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Groups
{
    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
     *
     * @param Server $server
     * @param AttributeDecorator
     */
    public function __construct(Server $server, AttributeDecorator $decorator)
    {
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->decorator = $decorator;
    }

    /**
     * @apiDefine _getGroup
     *
     * @apiParam (GET Parameter) {string[]} id Either a single id (group id) or a name (groupname) must be given (admin privilege required).
     * @apiParam (GET Parameter) {string[]} name Either a single id (group id) or a name (groupname) must be given (admin privilege required).
     *
     * @apiErrorExample {json} Error-Response (No admin privileges):
     * HTTP/1.1 403 Forbidden
     * {
     *      "status": 403,
     *      "data": {
     *          "error": "Balloon\\Exception\\Forbidden",
     *          "message": "submitted parameters require to have admin privileges",
     *          "code": 41
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Group not found):
     * HTTP/1.1 404 Not Found
     * {
     *      "status": 404,
     *      "data": {
     *          "error": "Balloon\\Exception\\NotFound",
     *          "message": "requested group was not found",
     *          "code": 53
     *      }
     * }
     *
     * @apiErrorExample {json} Error-Response (Invalid argument):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\InvalidArgument",
     *          "message": "provide either id (group id) or name (groupname)",
     *          "Code": 0
     *      }
     * }
     */

    /**
     * @apiDefine _getGroups
     *
     * @apiParam (GET Parameter) {string[]} id Either a single id (group id) as string or multiple as an array or a single name (groupname) as string or multiple groupnames as array must be given.
     * @apiParam (GET Parameter) {string[]} name Either a single id (groupid) as string or multiple as an array or a single name (groupname) as string or multiple groupnames as array must be given.
     */

    /**
     * Get group instance.
     *
     * @param string $id
     * @param string $name
     * @param bool   $require_admin
     *
     * @return Group
     */
    public function _getGroup(?string $id = null, ?string $name = null, bool $require_admin = false): Group
    {
        if (null !== $id || null !== $name || true === $require_admin) {
            if ($this->user->isAdmin()) {
                if (null !== $id && null !== $name) {
                    throw new Exception\InvalidArgument('provide either id (group id) or name (groupname)');
                }

                if (null !== $id) {
                    return $this->server->getGroupById(new ObjectId($id));
                }

                return $this->server->getGroupByName($name);
            }

            throw new ForbiddenException(
                    'submitted parameters require to have admin privileges',
                    ForbiddenException::ADMIN_PRIV_REQUIRED
                );
        }
    }

    /**
     * @api {get} /api/v2/groups/:id/member Get group member
     * @apiVersion 2.0.0
     * @apiName getMember
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission none
     * @apiDescription Request all member of a group
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/groups/member?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/member?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/member?name=logingroup&pretty"
     *
     * @apiSuccess {object[]} - List of user
     * @apiSuccess {string} -.id User ID
     * @apiSuccess {string} -.name Username
     * @apiSuccess {string} -.mail Mail address
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * [
     *  {
     *      "id": "544627ed3c58891f058b4613",
     *      "name": "ted",
     *      "mail": "test@example.org"
     *  }
     * ]
     *
     * @param string $id
     * @param string $name
     * @param array  $attributes
     */
    public function getMember(?string $id = null, ?string $name = null, array $attributes = []): Response
    {
        $result = $this->_getGroup($id, $name)->getResolvedMember();
        $body = [];

        foreach ($result as $member) {
            $body[] = $this->decorator->decorate($member, $attributes);
        }

        return (new Response())->setCode(200)->setBody($body);
    }

    /**
     * @api {get} /api/v2/groups/:id Get group attributes
     * @apiVersion 2.0.0
     * @apiName get
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission none
     * @apiDescription Get group attributes
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2/groups/attributes?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/attributes?pretty"
     * curl -XGET "https://SERVER/api/v2/groups/attributes?name=loginser&pretty"
     *
     * @apiSuccess (200 OK) {string} id group ID
     * @apiSuccess (200 OK) {string} name group name
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": "544627ed3c58891f058b46cd",
     *      "name": "test"
     * }
     *
     * @param string $id
     * @param string $name
     * @param string $attributes
     *
     * @return Response
     */
    public function get(?string $id = null, ?string $name = null, array $filter = [], array $attributes = []): Response
    {
        if ($id === null && $name === null) {
            $result = [];
            foreach ($this->server->getGroups($filter) as $group) {
                $result[] = $this->decorator->decorate($group, $attributes);
            }
        } else {
            $result = $this->decorator->decorate($this->_getGroup($id, $name), $attributes);
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {head} /api/v2/groups/:id Group exists
     * @apiVersion 2.0.0
     * @apiName postQuota
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Check if group account exists
     *
     * @apiExample Example usage:
     * curl -XHEAD "https://SERVER/api/v2/group"
     * curl -XHEAD "https://SERVER/api/v2/groups/544627ed3c58891f058b4611"
     * curl -XHEAD "https://SERVER/api/v2/group?name=logingroup"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $name
     * @param string $id
     *
     * @return Response
     */
    public function head(?string $id = null, ?string $name = null): Response
    {
        $result = $this->_getGroup($id, $name, true);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/group Create group
     * @apiVersion 2.0.0
     * @apiName postGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Create group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/group"
     *
     * @apiParam (POST Parameter) {string} name of the new group
     * @apiParam (POST Parameter) {string[]} ID of group member
     * @apiParam (POST Parameter) {string[]} Attributes
     *
     * @apiSuccess (200 OK) {string} id group ID
     * @apiSuccess (200 OK) {string} name group name
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "id": "544627ed3c58891f058b46cd",
     *      "name": "test"
     * }
     *
     * @param string $name
     * @param array  $member
     * @param array  $attributes
     *
     * @return Response
     */
    public function post(string $name, array $member, array $attributes = []): Response
    {
        $id = $this->server->addGroup($name, $member, $attributes);
        $result = $this->decorator->decorate($this->server->getGroupById($id));

        return (new Response())->setBody($result)->setCode(201);
    }

    /**
     * @api {patch} /api/v2/groups/:id Change group attributes
     * @apiVersion 2.0.0
     * @apiName patch
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Set attributes for group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/groups/attributes" -d '{"attributes": ["mail": "group@example.com"]}'
     * curl -XPOST "https://SERVER/api/v2/groups/attributes?{%22attributes%22:[%22mail%22:%22group@example.com%22]}""
     * curl -XPOST "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/attributes" -d '{"attributes": ["admin": "false"]}'
     * curl -XPOST "https://SERVER/api/v2/groups/quota?name=logingroup"  -d '{"attributes": ["admin": "false"]}'
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param string $name
     * @param string $id
     * @param array  $attributes
     *
     * @return Response
     */
    public function patch(array $attributes = [], ?string $id = null, ?string $name = null): Response
    {
        $group = $this->_getGroup($id, $name, true)->setAttributes($attributes);
        $result = $this->decorator->decorate($group);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {delete} /api/v2/groups/:id Delete group
     * @apiVersion 2.0.0
     * @apiName delete
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Delete group
     *
     * @apiExample Example usage:
     * curl -XDELETE "https://SERVER/api/v2/groups/544627ed3c58891f058b4611?force=1"
     * curl -XDELETE "https://SERVER/api/v2/group?name=logingroup"
     *
     * @apiParam (GET Parameter) {bool} [force=false] Per default the group gets disabled, if force is set
     * the group gets removed completely.
     *
     * @apiErrorExample {json} Error-Response (Can not delete yourself):
     * HTTP/1.1 400 Bad Request
     * {
     *      "status": 400,
     *      "data": {
     *          "error": "Balloon\\Exception\\Conflict",
     *          "message": "requested group was not found"
     *      }
     * }
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $name
     * @param string $id
     * @param bool   $force
     *
     * @return Response
     */
    public function delete(?string $id = null, ?string $name = null, bool $force = false): Response
    {
        $group = $this->_getGroup($id, $name, true);
        $group->delete($force);

        return (new Response())->setCode(204);
    }

    /**
     * @api {post} /api/v2/groups/:id/undelete Restore group
     * @apiVersion 2.0.0
     * @apiName postUndelete
     * @apiUse _getGroup
     * @apiGroup Group
     * @apiPermission admin
     * @apiDescription Restore deleted group
     *
     * @apiExample Example usage:
     * curl -XPOST "https://SERVER/api/v2/groups/544627ed3c58891f058b4611/undelete"
     * curl -XPOST "https://SERVER/api/v2/groups/undelete?group=logingroup"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 No Content
     *
     * @param string $name
     * @param string $id
     *
     * @return Response
     */
    public function postUndelete(?string $id = null, ?string $name = null): Response
    {
        $this->_getGroup($id, $name, true)->undelete();

        return (new Response())->setCode(204);
    }
}
