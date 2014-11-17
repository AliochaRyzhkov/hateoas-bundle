<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\Entity;

// JSON-API.
use GoIntegro\Bundle\HateoasBundle\JsonApi\ResourceEntityInterface;

class Mutator
{
    const DUPLICATED_MUTATOR = "A mutator for the resource type \"%s\" is already registered.";

    /**
     * @var array
     */
    private $mutators = [];

    /**
     * @param string $resourceType
     * @param ResourceEntityInterface $entity
     * @param array $data
     * @return \GoIntegro\Bundle\HateoasBundle\JsonApi\ResourceEntityInterface
     */
    public function update(
        $resourceType, ResourceEntityInterface $entity, array $data
    )
    {
        return isset($mutators[$resourceType])
            ? $this->mutators[$resourceType]->update($entity, $data)
            : $this->mutators['default']->update($entity, $data);
    }

    /**
     * @param MutatorInterface
     */
    public function addMutator(MutatorInterface $mutator, $resourceType)
    {
        if (isset($this->mutators[$resourceType])) {
            $message = sprintf(self::DUPLICATED_MUTATOR, $resourceType);
            throw new \ErrorException($message);
        }

        $this->mutators[$resourceType] = $mutator;
    }
}
