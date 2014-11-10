<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace HateoasInc\Bundle\ExampleBundle\Controller;

// Controladores.
use GoIntegro\Bundle\HateoasBundle\Controller\Controller,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
// HTTP.
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException,
    Symfony\Component\HttpKernel\Exception\BadRequestHttpException,
    Symfony\Component\HttpFoundation\Response;
// Entidades.
use HateoasInc\Bundle\ExampleBundle\Entity\User,
    HateoasInc\Bundle\ExampleBundle\Entity\Post;
// ACL.
use Symfony\Component\Security\Acl\Domain\ObjectIdentity,
    Symfony\Component\Security\Acl\Domain\UserSecurityIdentity,
    Symfony\Component\Security\Acl\Permission\MaskBuilder;

/**
 * @todo La búsqueda devuelve vacío con menos de cuatro caracteres.
 */
class PostsController extends Controller
{
    const AUTHOR_IS_OWNER = 'GoIntegro\\Bundle\\HateoasBundle\\Entity\\AuthorIsOwner',
       POSTS_SCHEMA = '/../Resources/raml/posts.schema.json';

    /**
     * @Route("/posts", name="api_get_posts", methods="GET")
     */
    public function getAllAction()
    {
        $params = $this->get('hateoas.request_parser')->parse();
        $posts = $this->get('hateoas.repo_helper')
            ->findByRequestParams($params);

        foreach ($posts as $post) {
            if (!$this->get('security.context')->isGranted('view', $post)) {
                throw new AccessDeniedHttpException('Unauthorized access!');
            }
        }

        $resources = $this->get('hateoas.resource_manager')
            ->createCollectionFactory()
            ->setPaginator($posts->getPaginator())
            ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resources)
            ->create()
            ->serialize();

        return $this->createETagResponse($json);
    }

    /**
     * @Route("/posts/{post}", name="api_get_post", methods="GET")
     */
    public function getOneAction(Post $post)
    {
        if (!$this->get('security.context')->isGranted('view', $post)) {
            throw new AccessDeniedHttpException('Unauthorized access!');
        }

        $params = $this->get('hateoas.request_parser')->parse();
        $posts = $this->get('hateoas.repo_helper')
            ->findByRequestParams($params);
        $resources = $this->get('hateoas.resource_manager')
            ->createCollectionFactory()
            ->setPaginator($posts->getPaginator())
            ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resources)
            ->create()
            ->serialize();

        return $this->createETagResponse($json);
    }

    /**
     * @Route("/posts", name="api_create_posts", methods="POST")
     */
    public function createAction()
    {
        $rawBody = $this->getRequest()->getContent();

        if (!$this->get('hateoas.json_coder')->matchSchema(
            $rawBody, __DIR__ . self::POSTS_SCHEMA
        )) {
            $message = $this->get('hateoas.json_coder')
                ->getSchemaErrorMessage();
            throw new BadRequestHttpException($message);
        }

        $data = $this->get('hateoas.json_coder')->decode($rawBody);
        $params = $this->get('hateoas.request_parser')->parse();
        $class = new \ReflectionClass($params->primaryClass);
        $post = $class->newInstance();

        if ($class->implementsInterface(self::AUTHOR_IS_OWNER)) {
            $post->setOwner($this->getUser());
        }

        foreach ($data[$params->primaryType] as $field => $value) {
            if ('links' == $field) continue;

            $method = 'set'
                . \Doctrine\Common\Util\Inflector::camelize($field);

            if ($class->hasMethod($method)) $post->$method($value);
        }

        // $post->setContent($data['posts']['content']);
        $errors = $this->get('validator')->validate($post);

        if (0 < count($errors)) {
            throw new BadRequestHttpException($errors);
        }

        $em = $this->get('doctrine.orm.entity_manager');
        $em->persist($post);
        $em->flush();

        $resource = $this->get('hateoas.resource_manager')
            ->createResourceFactory()
            ->setEntity($post)
            ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resource)
            ->create()
            ->serialize();

        return $this->createETagResponse($json, Response::HTTP_CREATED);
    }

    /**
     * @Route("/posts/{post}", name="api_update_posts", methods="PUT")
     */
    public function updateAction(Post $post)
    {
        if (!$this->get('security.context')->isGranted('edit', $post)) {
            throw new AccessDeniedHttpException('Unauthorized access!');
        }

        $rawBody = $this->getRequest()->getContent();

        if (!$this->get('hateoas.json_coder')->matchSchema(
            $rawBody, __DIR__ . self::POSTS_SCHEMA
        )) {
            $message = $this->get('hateoas.json_coder')
                ->getSchemaErrorMessage();
            throw new BadRequestHttpException($message);
        }

        $data = $this->get('hateoas.json_coder')->decode($rawBody);
        $post->setContent($data['posts']['content']);
        $errors = $this->get('validator')->validate($post);

        if (0 < count($errors)) {
            throw new BadRequestHttpException($errors);
        }

        $em = $this->get('doctrine.orm.entity_manager');
        $em->persist($post);
        $em->flush();

        $resource = $this->get('hateoas.resource_manager')
            ->createResourceFactory()
            ->setEntity($post)
            ->create();
        $json = $this->get('hateoas.resource_manager')
            ->createSerializerFactory()
            ->setDocumentResources($resource)
            ->create()
            ->serialize();

        return $this->createETagResponse($json);
    }
}
