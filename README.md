[GOintegro](http://www.gointegro.com/en/) / HATEOAS
===================================================
This is a library and Symfony 2 bundle that allows you to magically expose your Doctrine 2 mapped entities as resources in a [HATEOAS](http://www.ics.uci.edu/~fielding/pubs/dissertation/rest_arch_style.htm) API and supports the full spec of [JSON-API](http://jsonapi.org/) for serializing and fetching; sparse fields, includes, filtering, sorting, the works.

Pagination and [faceted searches](http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-facets.html) are supported as an extension to the JSON-API spec. (Although the extensions are not yet accompanied by the corresponding [profiles](http://jsonapi.org/extending/).)

Installation
============

Step 1: Download the Bundle
---------------------------

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require gointegro/hateoas-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Step 2: Enable the Bundle
-------------------------

Then, enable the bundle by adding the following line in the `app/AppKernel.php`
file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new GoIntegro\Bundle\HateoasBundle\GoIntegroHateoasBundle(),
        );

        // ...
    }

    // ...
}
?>
```

Step 3: Add these to your parameters.yml
----------------------------------------

```yaml
  # HATEOAS API
  api.base_url: "http://api.gointegro.com"
  api.url_path: "/api/v2"
  api.resource_class_path: "Rest2/Resource"
```

Step 3: Add these to your routing.yml
-------------------------------------

```yaml
# Place it underneath it all - it contains a catch-all route.
go_integro_hateoas:
    resource: "@GoIntegroHateoasBundle/Resources/config/routing.yml"
    prefix: /api/v2
```

Usage
=====

Have your entity implement the resource interface.

```php
<?php
use GoIntegro\Bundle\HateoasBundle\JsonApi\ResourceEntityInterface

class User implements ResourceEntityInterface {}
?>
```

You can now create a HATEOAS resource for it and serialize it as JSON-API. And, if your controller extends the HATEOAS controller, you can even return a neat HTTP response with the JSON-API Content-Type.

```php
<?php
use GoIntegro\Bundle\HateoasBundle\Controller\Controller,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class UsersController extends Controller {
    /**
     * @Route("/users/{user}", name="api_get_user", methods="GET")
     * @return \GoIntegro\Bundle\HateoasBundle\Http\JsonResponse
     */
    public function getUserAction(User $user) {
      $resourceManager = $serviceContainer->get('hateoas.resource_manager');
      $resource = $resourceManager->createResourceFactory()
        ->setEntity($user)
        ->create();

      $json = $resourceManager->createSerializerFactory()
        ->setResourceDocument($resource)
        ->create()
        ->serialize();

      return $this->createETagResponse($json);
    }
?>
```

Seems like it could get awfully repetitive, doesn't it?

That's why you don't have to.

Just register your entity as a "magic service".

```yaml
# The resource_type *must* match the calculated type - for now.
go_integro_hateoas:
  json_api:
    magic_services:
      - resource_type: users
        entity_class: GoIntegro\Bundle\ExampleBundle\Entity\User
      - resource_type: posts
        entity_class: GoIntegro\Bundle\ExampleBundle\Entity\Post
      - resource_type: comments
        entity_class: GoIntegro\Bundle\ExampleBundle\Entity\Comment
```

And you get the following for free.

```
/users
/users/1
/users/1,2,3
/users/1/name
/users/1/linked/posts
/posts/1/linked/author
/posts?author=1
/posts?author=1,2,3
/users?sort=name,-birth-date
/users?include=posts,posts.comments
/users?fields=name,email
/users?include=posts,posts.comments&fields[users]=name&fields[posts]=content
/users?page=1
/users?page=1&size=10
```

And more. And any combination. Sweet.

But you need to have some control over what you expose, right? Got you covered.

You can optionally define a class like this for your entity, and optionally define any of the properties and methods you will see within.

```
<?php
namespace GoIntegro\Bundle\ExampleBundle\Rest2\Resource;

// Symfony 2.
use Symfony\Component\DependencyInjection\ContainerAwareInterface,
  Symfony\Component\DependencyInjection\ContainerAwareTrait;
// HATEOAS.
use GoIntegro\Bundle\HateoasBundle\JsonApi\EntityResource;

class UserResource extends EntityResource implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var array
     */
    public static $fieldWhitelist = ['name', 'surname', 'email'];
    /**
     * You wouldn't ever use both a blacklist and a whitelist.
     * @var array
     */
    public static $fieldBlacklist = ['password'];
    /**
     * @var array
     */
    public static $relationshipBlacklist = ['groups'];
    /**
     * These appear as top-level links but not in the resource object.
     * @var array
     */
    public static $linkOnlyRelationships = ['followers'];

    /**
     * By injecting a field we can have both the JSON-API reserved key "type" and our own "user-type" attribute in the resource object.
     * @return string
     * @see http://jsonapi.org/format/#document-structure-resource-object-attributes
     */
    public function injectUserType()
    {
        return $this->entity->getType();
    }

    /**
     * We can use services if we implement the ContainerAwareInterface.
     * @return string
     */
    public function injectSomethingExtraordinary()
    {
        return $this->container->get('mystery_machine')->amaze();
    }
}
?>
```

Check out the unit tests for more details.
