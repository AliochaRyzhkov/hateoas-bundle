<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\Controller;

// Controladores.
use Symfony\Bundle\FrameworkBundle\Controller\Controller as SymfonyController,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
    Symfony\Component\HttpFoundation\JsonResponse;
// Colecciones.
use Doctrine\Common\Collections\Collection;
// HTTP.
use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpKernel\Exception\NotFoundHttpException,
    Symfony\Component\HttpKernel\Exception\ConflictHttpException,
    Symfony\Component\HttpKernel\Exception\BadRequestHttpException,
    Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
// JSON-API.
use GoIntegro\Bundle\HateoasBundle\JsonApi\Exception\DocumentTooLargeHttpException,
    GoIntegro\Bundle\HateoasBundle\JsonApi\ResourceEntityInterface,
    GoIntegro\Bundle\HateoasBundle\JsonApi\Request\Params;
// Utils.
use GoIntegro\Bundle\HateoasBundle\Util\Inflector;
// Security.
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
// Validator.
use GoIntegro\Bundle\HateoasBundle\Entity\Validation\EntityConflictExceptionInterface,
    GoIntegro\Bundle\HateoasBundle\Entity\Validation\ValidationExceptionInterface;

/**
 * Permite probar la flexibilidad de la biblioteca.
 * @todo Refactor.
 */
class MagicController extends SymfonyController
{
    use CommonResponseTrait;

    const RESOURCE_LIMIT = 50,
        ERROR_ACCESS_DENIED = "Access to the resource was denied.",
        ERROR_RESOURCE_NOT_FOUND = "The resource was not found.",
        ERROR_RELATIONSHIP_NOT_FOUND = "No relationship by that name found.",
        ERROR_FIELD_NOT_FOUND = "No field by that name found.",
        ERROR_MISSING_DATA = "No data set found for the resource with the Id \"%s\".",
        ERROR_MISSING_ID = "A data set provided is missing the Id.";

    /**
     * @Route("/{primaryType}/{id}/linked/{relationship}", name="hateoas_magic_relation", methods="GET")
     * @param string $primaryType
     * @param string $id
     * @param string $relationship
     * @throws HttpException
     * @throws NotFoundHttpException
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.14
     */
    public function getRelationAction($primaryType, $id, $relationship)
    {
        $params = $this->get('hateoas.request_parser')->parse();

        if (empty($params->primaryClass)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        $entity = $this->getDoctrine()
            ->getManager()
            ->find($params->primaryClass, $id);

        if (empty($entity)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        // @todo Intentar evitar crear el recurso. Necesitamos poder manejar los blacklists desde la metadata, o algo así.
        $primaryResource = $this->get('hateoas.resource_manager')
            ->createResourceFactory()
            ->setEntity($entity)
            ->create();
        $metadata = $primaryResource->getMetadata();
        $json = NULL;
        $relation = NULL;
        $relatedResource = NULL;

        if ($metadata->isRelationship($relationship)) {
            $relation = $primaryResource->callGetter($relationship);
        } else {
            throw new NotFoundHttpException(
                self::ERROR_RELATIONSHIP_NOT_FOUND
            );
        }

        if ($metadata->isToManyRelationship($relationship)) {
            if ($relation instanceof Collection) {
                $relation = $relation->toArray();
            }

            if (Controller::DEFAULT_RESOURCE_LIMIT < count($relation)) {
                throw new DocumentTooLargeHttpException;
            }

            $relatedResource = $this->get('hateoas.resource_manager')
                ->createCollectionFactory()
                ->addEntities($relation)
                ->create();
        } elseif ($metadata->isToOneRelationship($relationship)) {
            $relatedResource = empty($relation)
                ? NULL
                : $this->get('hateoas.resource_manager')
                    ->createResourceFactory()
                    ->setEntity($relation)
                    ->create();
        } else {
            throw new NotFoundHttpException(
                self::ERROR_RELATIONSHIP_NOT_FOUND
            );
        }

        $json = empty($relatedResource)
            ? NULL
            : $this->get('hateoas.resource_manager')
                ->createSerializerFactory()
                ->setDocumentResources($relatedResource)
                ->create()
                ->serialize();

        return $this->createETagResponse($json);
    }

    /**
     * @Route("/{primaryType}/{id}/{field}", name="hateoas_magic_field", methods="GET")
     * @param string $primaryType
     * @param string $id
     * @param string $relationship
     * @throws HttpException
     * @throws NotFoundHttpException
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.14
     */
    public function getFieldAction($primaryType, $id, $field)
    {
        $params = $this->get('hateoas.request_parser')->parse();

        if (empty($params->primaryClass)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        $entity = $this->getDoctrine()
            ->getManager()
            ->find($params->primaryClass, $id);

        if (empty($entity)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        // @todo Intentar evitar crear el recurso. Necesitamos poder manejar los blacklists desde la metadata, o algo así.
        $resource = $this->get('hateoas.resource_manager')
            ->createResourceFactory()
            ->setEntity($entity)
            ->create();
        $metadata = $resource->getMetadata();
        $json = NULL;

        if ($metadata->isField($field)) {
            $json = $resource->callGetter($field);
        } else {
            throw new NotFoundHttpException(self::ERROR_FIELD_NOT_FOUND);
        }

        return $this->createETagResponse($json);
    }

    /**
     * @Route("/{primaryType}/{ids}", name="hateoas_magic_one", methods="GET")
     * @param string $primaryType
     * @param string $ids
     */
    public function getByIdsAction($primaryType, $ids)
    {
        $params = $this->get('hateoas.request_parser')->parse();
        $entities = $this->getEntitiesFromParams($ids);

        foreach ($entities as $entity) {
            if (!$this->get('security.context')->isGranted('view', $entity)) {
                throw new AccessDeniedHttpException(self::ERROR_ACCESS_DENIED);
            }
        }

        $resources = 1 < count($entities)
            ? $this->get('hateoas.resource_manager')
                ->createCollectionFactory()
                ->addEntities($entities)
                ->create()
            : $this->get('hateoas.resource_manager')
                ->createResourceFactory()
                ->setEntity(reset($entities))
                ->create();

        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resources)
            ->create()
            ->serialize();

        return $this->createETagResponse($json);
    }

    /**
     * @Route("/{primaryType}", name="hateoas_magic_all", methods="GET")
     * @param string $primaryType
     * @throws NotFoundHttpException
     * @throws DocumentTooLargeHttpException
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.14
     */
    public function getWithFiltersAction($primaryType)
    {
        $params = $this->get('hateoas.request_parser')->parse();

        if (empty($params->primaryClass)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        $resources = NULL;
        $params = $this->get('hateoas.request_parser')->parse();
        $entities = $this->get('hateoas.repo_helper')
            ->findByRequestParams($params)
            ->filter(function($entity) {
                $this->get('security.context')->isGranted('view', $entity);
            });

        if (0 == count($entities)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        if (Controller::DEFAULT_RESOURCE_LIMIT < count($entities)) {
            throw new DocumentTooLargeHttpException;
        }

        $resources = $this->get('hateoas.resource_manager')
            ->createCollectionFactory()
            ->setPaginator($entities->getPaginator())
            ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resources)
            ->create()
            ->serialize();

        return $this->createETagResponse($json);
    }

    /**
     * @Route("/{primaryType}", name="hateoas_magic_create", methods="POST")
     * @param string $primaryType
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     * @see http://jsonapi.org/format/#crud-creating-resources
     * @todo Support multi-create.
     * @todo Rollback everything if anything goes wrong.
     */
    public function createAction($primaryType)
    {
        $rawBody = $this->getRequest()->getContent();

        $params = $this->get('hateoas.request_parser')->parse();
        $raml = $this->get('hateoas.raml.finder')->find($params->primaryType);

        if (!$this->get('hateoas.json_coder')->matchSchema($rawBody, $raml)) {
            $message = $this->get('hateoas.json_coder')
                ->getSchemaErrorMessage();
            throw new BadRequestHttpException($message);
        }

        $data = $this->get('hateoas.json_coder')->decode($rawBody);

        try {
            $entity = $this->get('hateoas.entity.builder')->create($data);
        } catch (AccessDeniedException $e) {
            throw new AccessDeniedHttpException($e->getMessage(), $e);
        } catch (EntityConflictExceptionInterface $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (ValidationExceptionInterface $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $resource = $this->get('hateoas.resource_manager')
            ->createResourceFactory()
            ->setEntity($entity)
            ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resource)
            ->create()
            ->serialize();

        return $this->createETagResponse($json, Response::HTTP_CREATED);
    }

    /**
     * @Route("/{primaryType}/{ids}", name="hateoas_magic_update", methods="PUT")
     * @param string $primaryType
     * @param string $ids
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     * @see http://jsonapi.org/format/#crud-updating
     * @todo Rollback everything if anything goes wrong.
     */
    public function updateAction($primaryType, $ids)
    {
        $params = $this->get('hateoas.request_parser')->parse();
        $entities = $this->getEntitiesFromParams($params);

        foreach ($entities as $entity) {
            if (!$this->get('security.context')->isGranted('edit', $entity)) {
                throw new AccessDeniedHttpException(self::ERROR_ACCESS_DENIED);
            }

            $data = $this->getDataForEntity($entity);

            try {
                // @todo Improve the signature of update().
                $entity = $this->get('hateoas.entity.mutator')
                    ->update($entity, $data);
            } catch (AccessDeniedException $e) {
                throw new AccessDeniedHttpException($e->getMessage(), $e);
            } catch (EntityConflictExceptionInterface $e) {
                throw new ConflictHttpException($e->getMessage(), $e);
            } catch (ValidationExceptionInterface $e) {
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
        }

        $resources = 1 < count($entities)
            ? $this->get('hateoas.resource_manager')
                ->createCollectionFactory()
                ->addEntities($entities)
                ->create()
            : $this->get('hateoas.resource_manager')
                ->createResourceFactory()
                ->setEntity(reset($entities))
                ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resources)
            ->create()
            ->serialize();

        return $this->createETagResponse($json);
    }

    /**
     * @param Params $params
     * @return array
     */
    private function getEntitiesFromParams(Params $params)
    {
        if (Controller::DEFAULT_RESOURCE_LIMIT < count($params->primaryIds)) {
            throw new DocumentTooLargeHttpException;
        }

        if (empty($params->primaryClass)) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        $entities = $this->getDoctrine()
            ->getManager()
            ->getRepository($params->primaryClass)
            ->findById($params->primaryIds);

        if (
            empty($entities)
            || count($entities) !== count($params->primaryIds)
        ) {
            throw new NotFoundHttpException(self::ERROR_RESOURCE_NOT_FOUND);
        }

        return $entities;
    }

    /**
     * @param ResourceEntityInterface $entity
     * @return $data
     * @throws BadRequestHttpException
     * @todo Move to parser.
     */
    private function getDataForEntity(ResourceEntityInterface $entity)
    {
        $rawBody = $this->getRequest()->getContent();
        $data = $this->get('hateoas.json_coder')->decode($rawBody);
        $params = $this->get('hateoas.request_parser')->parse();
        $entityData = NULL;

        if (isset($data[$params->primaryType]['id'])) {
            if (
                (string) $entity->getId()
                    === $data[$params->primaryType]['id']
            ) {
                $entityData = $data;
            } else {
                $message = sprintf(self::ERROR_MISSING_DATA, $entity->getId());
                throw new BadRequestHttpException($message);
            }
        } else {
            foreach ($data[$params->primaryType] as $datum) {
                if (!isset($datum['id'])) {
                    throw new BadRequestHttpException(self::ERROR_MISSING_ID);
                } elseif ((string) $entity->getId() === $datum['id']) {
                    $entityData = $datum;
                    break;
                }
            }

            if (empty($entityData)) {
                $message = sprintf(self::ERROR_MISSING_DATA, $entity->getId());
                throw new BadRequestHttpException($message);
            }
        }

        $raml = $this->get('hateoas.raml.finder')->find($params->primaryType);

        if (!$this->get('hateoas.json_coder')->matchSchema(
            $entityData, $raml
        )) {
            $message = $this->get('hateoas.json_coder')
                ->getSchemaErrorMessage();
            throw new BadRequestHttpException($message);
        }

        return $entityData;
    }
}
