# [GOintegro](http://www.gointegro.com/en/) / HATEOAS

[![Build Status](https://travis-ci.org/skqr/hateoas-bundle.svg?branch=master)](https://travis-ci.org/skqr/hateoas-bundle) [![Code Climate](https://codeclimate.com/github/skqr/hateoas-bundle/badges/gpa.svg)](https://codeclimate.com/github/skqr/hateoas-bundle)

This is a library and Symfony 2 bundle that uses a Doctrine 2 entity map and a [RAML](http://raml.org/) API definition to conjure a [HATEOAS](http://www.ics.uci.edu/~fielding/pubs/dissertation/rest_arch_style.htm) API, following the [JSON-API](http://jsonapi.org/) specification.

You don't get scaffolds. You get a working API.

You get a working API with features sweeter than [a Bobcat's self-esteem](http://s3.amazonaws.com/theoatmeal-img/comics/bobcats_thursday/mirror.png).

## Features

Here's what I mean.

* Modeling, documenting, and creating your API are [one step](#api-definition).
* Flat, referenced JSON serialization.
  * Clear distinction between scalar fields and linked resources.
* Magic controllers.
  * Fetching resources, with support for:
    * Sparse fields;
    * Linked resources expansion;
    * [Standarized filtering](#query-filters) and sorting;
    * Pagination;
    * Resource metadata, such as facets in a search.
  * Altering resources, with support for:
    * Processing multiple actions in one request;
    * Request validation using JSON schema;
    * [Entity validation](#validation) using Symfony's validator;
    * [Create, update, and delete](#creating-updating-and-deleting) out of the box;
    * Assign services to handle any of the above for specific resources.
* [Metadata caching](#caching), similar to that of Doctrine 2;
  * Redis,
  * Or Memcached.

Here's what you'll need.

* A Doctrine 2 entity map;
* A RAML API definition;
* At least one Symfony 2 security voter.

# Try it out

Check out [the example app project](https://github.com/skqr/hateoas-bundle-example), so you can feel the magic in your finger tips without much ado.

___

# Installation

## Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require gointegro/hateoas-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

## Step 2: Enable the Bundle

Then, enable the bundle by adding the following line in the `app/AppKernel.php`
file of your project:

```php
<?php
// app/AppKernel.php

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new GoIntegro\Bundle\HateoasBundle\GoIntegroHateoasBundle(),
        );
    }
}
?>
```

## Step 3: Add these parameters

```yaml
# app/config/parameters.yml

# HATEOAS API
api.base_url: "http://api.gointegro.com"
api.url_path: "/api/v2"
api.resource_class_path: "Rest2/Resource"
```

## Step 4: Add these routes

```yaml
# app/config/routing.yml

# Place it underneath it all - it contains a catch-all route.
go_integro_hateoas:
    resource: "@GoIntegroHateoasBundle/Resources/config/routing.yml"
    prefix: /api/v2
```

___

# Usage

Have your entity implement the resource interface.

```php
<?php
use GoIntegro\Bundle\HateoasBundle\JsonApi\ResourceEntityInterface

class User implements ResourceEntityInterface {}
?>
```

Just register your entity as a *magic service*.

```yaml
# app/config/config.yml

go_integro_hateoas:
  json_api:
    magic_services:
      - resource_type: users
        entity_class: GoIntegro\Bundle\ExampleBundle\Entity\User
        raml_doc: %kernel.root_dir%/../src/HateoasInc/Bundle/ExampleBundle/Resources/raml/users.raml
  cache: ~
```

And you get the following for free.

```
GET /users
GET /users/1
GET /users/1,2,3
GET /users/1/name
GET /users/1/linked/posts
GET /posts/1/linked/owner
GET /posts?owner=1
GET /posts?owner=1,2,3
GET /users?sort=name,-birth-date
GET /users?include=posts,posts.comments
GET /users?fields=name,email
GET /users?include=posts,posts.comments&fields[users]=name&fields[posts]=content
GET /users?page=1
GET /users?page=1&size=10
```

And any combination.

You also get these.

```
POST /users

PUT /users/1
PUT /users/1,2,3

DELETE /users/1
DELETE /users/1,2,3
```

And you get to link or unlink resources thus.

```
POST /users/1/links/user-groups

PUT /users/1/links/user-groups

DELETE /users/1/links/user-groups
DELETE /users/1/links/user-groups/1
DELETE /users/1/links/user-groups/1,2,3
```

Sweet, right?

> The `resource_type` **must** match the calculated type - for now. E.g. `UserGroup`, `user-groups`.

# API Definition

But first - you need to define your API.

The HATEOAS bundle needs to know which magic actions you want enabled for your resources. That's why each of them has a `raml_doc` key in the `config.yml`.

```yaml
# app/config/config.yml
go_integro_hateoas:
  json_api:
    magic_services:
      - resource_type: users
        entity_class: GoIntegro\Bundle\ExampleBundle\Entity\User
        raml_doc: %kernel.root_dir%/../src/HateoasInc/Bundle/ExampleBundle/Resources/raml/users.raml
```

Check out the [RAML docs](http://raml.org/docs.html) in order to learn more about what your API could look like. Nevertheless, the bundle will only pay attention to the [JSON-API URLs](http://jsonapi.org/format/#document-structure-resource-urls).

## JSON Schema

Requests that create or update resources have the content of their bodies validated against the schema defined in the RAML for that resource and method.

Since bodies in JSON-API [look pretty similar](http://jsonapi.org/format/#document-structure-resource-representations) whether you are fetching, creating, or updating, you can use a *default* schema, defined in the root of the RAML document with the resource type as key.

For example, this could be the RAML definition for the `/users` resource.

```yaml
#%RAML 0.8
title: HATEOAS Inc. Example API
version: v1
baseUri: http://api.hateoas-example.net/{version}
mediaType: application/vnd.api+json
schemas:
  - users: !include users.schema.json
/users:
```

# Resources

But you need to have some control over what you expose, right? Got you covered.

You can optionally define a class like this for your entity, and optionally define any of the properties and methods you will see within.

```php
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

# Entities

This bundle is pretty entity-centric. The way your entities look and the relationships between them, as mapped in Doctrine 2, are essential to the intelligence this bundle employs in determining what your API should look like.

## Security

Access control is handled by [Symfony's Security Component](http://symfony.com/doc/current/components/security/introduction.html), so either [security voters](http://symfony.com/doc/current/cookbook/security/voters_data_permission.html) or [ACL](http://symfony.com/doc/current/cookbook/security/acl.html) must be configured.

If you don't want security at all, just configure a single voter accepting anything that implements `GoIntegro\Bundle\HateoasBundle\JsonApi\ResourceEntityInterface`. Not the best advice ever, though.

What about pagination? I'm pretty sure `isGranted` will not be called against every single entity in the collection - right?

Absolutely.

In order to address this, we came up with a really simple solution. We mixed the security voter and custom filter interfaces.

Have your voter/filter implement `GoIntegro\Bundle\HateoasBundle\Security\VoterFilterInterface` and tag it with both the `security.voter` and `hateoas.repo_helper.filter` tags.

```yaml
# src/Example/Bundle/AppBundle/Resources/config/services.yml

  security.access.user_voter:
    class: HateoasInc\Bundle\ExampleBundle\Security\Authorization\Voter\UserVoter
    public: false
    tags:
      - { name: security.voter }
      - { name: hateoas.repo_helper.filter }
```

Your access control logic for viewing the entity should be expressed in the shape of a security voter within the method `vote`, and the shape of a fetch request filter within the method `filter`.

Voilà.

## Validation

Default validation is handled by [Symfony's Validator Component](http://symfony.com/doc/current/book/validation.html), so you can configure basic validation right on your entities.

Also, you can extend the validator by [writing your own constraints](http://symfony.com/doc/current/cookbook/validation/custom_constraint.html).

Since [constraints can be services](http://symfony.com/doc/current/cookbook/validation/custom_constraint.html#constraint-validators-with-dependencies), this means that you are probably not going to need to create custom builders or mutators.

Violations of the [unique entity constraint](http://symfony.com/doc/current/reference/constraints/UniqueEntity.html) ultimately result in a [409 HTTP response status](http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.10), *conflict*. :fire:

> JSON-API requires 409 to only be used when attempting to create [a relationship that already exists](http://jsonapi.org/format/#crud-updating-responses). We are expanding its application to include any instance in which validation fails due to a conflict with another resource.

If you'd like to create a custom constraint in which a violation is taken to mean conflict between resources, just have it implement `GoIntegro\Bundle\HateoasBundle\Entity\Validation\ConflictConstraintInterface`.

## Transactions

Creating, updating, or deleting multiple resources on a single request is supported by JSON-API - [but no partial updates are allowed](http://jsonapi.org/format/#crud).

We use [explicit transaction demarcation](http://doctrine-orm.readthedocs.org/en/latest/reference/transactions-and-concurrency.html#approach-2-explicitly) on the controller that handles creating, updating, and deleting resources *magically* so that this rule is enforced.

## Creating, updating, and deleting

As mentioned, services for creating, updating, and deleting resources are provided by default.

But what about your business logic? That wouldn't fly far.

You can register services that handle each of these operations for specific resources called *builders*, *mutators*, and *deleters* by tagging them.

```yaml
# src/Example/Bundle/AppBundle/Resources/config/services.yml

example.your_resource.builder:
    class: Example\Bundle\AppBundle\Entity\YourEntityBuilder
    public: false
    arguments:
      - @doctrine.orm.entity_manager
      - @validator
      - @security.context
    tags:
      -  { name: hateoas.entity.builder, resource_type: your_resource }
```

This builder class should implement a certain interface. Here are the available tags and interfaces.

Tag | Interface
--- | ---------
hateoas.entity.builder | `GoIntegro\Bundle\HateoasBundle\Entity\BuilderInterface`
hateoas.entity.mutator | `GoIntegro\Bundle\HateoasBundle\Entity\MutatorInterface`
hateoas.entity.deleter | `GoIntegro\Bundle\HateoasBundle\Entity\DeleterInterface`

# Extending

## [Muggle](https://en.wikipedia.org/wiki/Muggle) Controllers

If you want to override the magic controllers for whatever reason, just create a good old Symfony 2 controller.

You can use entities implementing the `ResourceEntityInterface` with the services provided by the HATEOAS bundle quite independently.

Here's a pretty basic example.

```php
<?php
use GoIntegro\Bundle\HateoasBundle\Controller\Controller,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class UsersController extends Controller
{
    /**
     * @Route("/users/{user}", name="api_get_user", methods="GET")
     * @return \GoIntegro\Bundle\HateoasBundle\Http\JsonResponse
     */
    public function getUserAction(User $user)
    {
      $resourceManager = $serviceContainer->get('hateoas.resource_manager');
      $resource = $resourceManager->createResourceFactory()
        ->setEntity($user)
        ->create();

      $json = $resourceManager->createSerializerFactory()
        ->setDocumentResources($resource)
        ->create()
        ->serialize();

      return $this->createETagResponse($json);
    }
?>
```

Check out the bundle's `services.yml` for a glimpse at the HATEOAS arsenal we keep there.

## Ghosts :ghost:

I know what you're thinking - what if my resource does not have an entity? Am I left to fend for myself in the cold dark night?

Not a chance. Ghosts are there with you, in the dark. To help you out.

*Ghosts* are what you create ghost-resources from instead of persisted entities. They are created within a custom HATEOAS controller, and can be fed to the resource factory in lieu of an entity. They define their relationships rather than the ORM knowing about them beforehand.

What you use for an Id, and the extent to which you use them is entirely up to you.

```php
<?php
namespace GoIntegro\Bundle\SomeBundle\Rest2\Ghost;

// Entidades.
use GoIntegro\Bundle\SomeBundle\Entity\Star;
// JSON-API.
use GoIntegro\Bundle\HateoasBundle\JsonApi\GhostResourceEntity,
    GoIntegro\Bundle\HateoasBundle\Metadata\Resource\ResourceRelationship,
    GoIntegro\Bundle\HateoasBundle\Metadata\Resource\ResourceRelationships;
// Colecciones.
use Doctrine\Common\Collections\ArrayCollection;

class StarCluster implements GhostResourceEntity
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var Star
     */
    private $brightestStar;
    /**
     * @var ArrayCollection
     */
    private $stars;

    /**
     * @param string $id
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->stars = new ArrayCollection;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Star $star
     * @return self
     */
    public function setBrightestStar(Star $star)
    {
        $this->brightestStar = $star;

        return $this;
    }

    /**
     * @return Star
     */
    public function getBrightestStar()
    {
        return $this->brightestStar;
    }

    /**
     * @return ArrayCollection
     */
    public function getStars()
    {
        return $this->stars;
    }

    /**
     * @param \GoIntegro\Bundle\SomeBundle\Entity\Star $star
     * @return self
     */
    public function addStar(Star $star)
    {
        $this->stars[] = $star;

        return $this;
    }

    /**
     * @return ResourceRelationships
     */
    public static function getRelationships()
    {
        $relationships = new ResourceRelationships;
        $relationships->toMany['stars'] = new ResourceRelationship(
            'GoIntegro\Bundle\SomeBundle\Entity\Star',
            'stars', // resource type
            'stars', // resource sub-type
            'toMany', // relationship kind
            'stars', // relationship name
            'stars' // mapping field
        );
        $relationships->toOne['brightest-star'] = new ResourceRelationship(
            'GoIntegro\Bundle\SomeBundle\Entity\Star',
            'stars',
            'stars',
            'toOne',
            'brightest-star',
            'brightestStar'
        );

        return $relationships;
    }
}
?>
```

## Query filters

The [standard fetch request filters](http://jsonapi.org/format/#fetching-filtering) are bundled along with the bundle.

This is the service providing them.

```yaml
# GoIntegro/HateoasBundle/Resources/config/services.yml

  hateoas.repo_helper.default_filter:
    class: GoIntegro\Bundle\HateoasBundle\JsonApi\Request\DefaultFilter
    public: false
    tags:
      - name: hateoas.repo_helper.filter
```

If you're somewhat familiar with [tagged services](http://symfony.com/doc/current/components/dependency_injection/tags.html), you probably guessed that you can add your own.

Just have your filter class implement `GoIntegro\Bundle\HateoasBundle\JsonApi\Request\FilterInterface`, and add the `hateoas.repo_helper.filter` tag when you declare it as a service.

> Your filter should use the entity and filter parameters it gets in order to decide whether or not to act. Make sure a single class doesn't get [too much filtering responsibility](https://en.wikipedia.org/wiki/Single_responsibility_principle).

# Testing

The bundle comes with a comfy PHPUnit test case designed to make HATEOAS API functional tests.

A simple HTTP client makes the request and assertions are made using [JSON schemas](http://json-schema.org/).

```php
<?php
namespace GoIntegro\Entity\Suite;

// Testing.
use GoIntegro\Test\PHPUnit\ApiTestCase;
// Fixtures.
use GoIntegro\DataFixtures\ORM\Standard\SomeDataFixture;

class SomeResourceTest extends ApiTestCase
{
    const RESOURCE_PATH = '/api/v2/some-resource',
        RESOURCE_JSON_SCHEMA = '/schemas/some-resource.json';

    /**
     * Doctrine 2 data fixtures to load *before the test case*.
     * @return array <FixtureInterface>
     */
    protected static function getFixtures()
    {
        return array(new SomeDataFixture);
    }

    public function testGettingMany200()
    {
        /* Given... (Fixture) */
        $url = $this->getRootUrl() . self::RESOURCE_PATH;
        $client = $this->createHttpClient($url);
        /* When... (Action) */
        $transfer = $client->exec();
        /* Then... (Assertions) */
        $this->assertResponseOK($client);
        $this->assertJsonApiSchema($transfer);
        $schema = __DIR__ . self::RESOURCE_JSON_SCHEMA;
        $this->assertJsonSchema($schema, $transfer);
    }

    public function testGettingSortedBySomeCustomField400()
    {
        /* Given... (Fixture) */
        $url = $this->getRootUrl()
            . self::RESOURCE_PATH
            . '?sort=some-custom-field';
        $client = $this->createHttpClient($url);
        /* When... (Action) */
        $transfer = $client->exec();
        /* Then... (Assertions) */
        $this->assertResponseBadRequest($client);
    }
}
?>
```

# Error handling

JSON-API covers [how to inform about errors](http://jsonapi.org/format/#errors) as well.

Our implementation isn't trully as complete as could be, but you can tell Twig to use our ExceptionController instead of its own in order to have your errors serialized properly.

```yaml
# app/config/config.yml

twig:
  exception_controller: 'GoIntegro\Bundle\HateoasBundle\Controller\ExceptionController::showAction'
```

# Fetching multiple URLs

Here's something useful but not RESTful.

You can use the */multi* action to fetch several JSON-API URLs, and this won't even result in an additional HTTP request.

```
/multi?url[]=%2Fapi%2Fv1%2Fusers&url[]=%2Fapi%2Fv1%2Fposts
```

The URLs just need to be encoded, but you can use the full set of JSON-API functionality supported.

A *blender* service wil make sure to notify you if, by chance, the URLs provided are not mergeable.

# Caching

Yeah. These processes are not cheap.

You might want to hold on to that metadata you've mined or that resource you've serialized for a while.

## Resource Metadata

The resource metadata describes a resource type. It describes its name, fields, its relationships to other resources, and other such things.

It's akin to the Doctrine 2 entity mapping, `Doctrine\ORM\Mapping\ClassMetadata`.

We obtain this class by inspecting an entity's mapping and its class, using the ORM and reflection.

You can cache a resource type's metadata for as long as neither of these two things change.

Here's how. Add this parameter.

```yaml
# app/config/parameters.yml

parameters:
    hateoas.resource_cache.class: GoIntegro\Bundle\HateoasBundle\JsonApi\ArrayResourceCache
```

Cache type | Parameter value
---------- | ---------
Array (none) | `GoIntegro\Bundle\HateoasBundle\JsonApi\ArrayResourceCache`
Redis | `GoIntegro\Bundle\HateoasBundle\JsonApi\RedisResourceCache`
Memcached | `GoIntegro\Bundle\HateoasBundle\JsonApi\MemcachedResourceCache`

You can customize your Redis or Memcached configuration by using any of the following options. Below are the default values.

```yaml
# app/config/config.yml

go_integro_hateoas:
  cache: ~
    # resource:
    #   redis:
    #     parameters:
    #       scheme: tcp
    #       host: 127.0.0.1
    #       port: 6379
    #     options: []
    #   memcached: ~
    #     persistent_id: null
    #     servers:
    #       - host: 127.0.0.1
    #         port: 11211
```

## HTTP Response

Fetch responses are all delivered with an [Etag](https://en.wikipedia.org/wiki/HTTP_ETag).

The Etag is created from the full body of the response, so it accurately represents the JSON-API document you're fetching, along with its includes, sparse fields, meta, etc.

Etags on requests are checked [using Symfony](http://symfony.com/doc/current/book/http_cache.html#validation-with-the-etag-header).

___

# Feedback

Feel free to **open an issue** if you have valuable (or otherwise) feedback. Hoping to hear from you (either way).

If you're going to dare rocking so hard as to make a pull request, use the `master` branch for fixes, and the `develop` branch for proposed features.

New code should not exceed the legendary eighty char boundary, and [be fully documented](http://www.phpdoc.org/docs/latest/index.html).

# Roadmap

Any [issue labeled *enhancement* goes](https://github.com/GoIntegro/hateoas-bundle/issues?q=is%3Aissue+is%3Aopen+label%3Aenhancement+author%3Askqr+).

# Disclaimer

You might have [noticed something fishy](http://cdn.duitang.com/uploads/item/201203/12/20120312155233_AaA8J.jpeg) in the PHP snippets above.

[Closing tags](http://php.net/manual/en/language.basic-syntax.phptags.php).

I don't actually support using them, [my text editor](http://www.sublimetext.com/3) just [goes crazy](http://www.emlii.com/images/article/2014/02/52f7aceace997.gif) if I don't.
