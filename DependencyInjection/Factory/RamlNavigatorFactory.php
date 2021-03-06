<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\DependencyInjection\Factory;

// JSON.
use GoIntegro\Json\JsonCoder;
// RAML.
use GoIntegro\Raml;

class RamlNavigatorFactory
{
    const ERROR_PARAM_TYPE = "Cannot find RAML with the given clue; a resource type or entity was expected.";

    /**
     * @var Raml\DocParser
     */
    private $parser;

    /**
     * @param Raml\DocParser $parser
     * @param $ramlDocPath
     */
    public function __construct(Raml\DocParser $parser, $ramlDocPath)
    {
        $this->parser = $parser;

        if (!is_readable($ramlDocPath)) {
            throw new \RuntimeException(self::ERROR_PARAM_TYPE);
        }

        // @todo Esta verificación debería estar en el DI.
        $this->ramlDoc = $parser->parse($ramlDocPath);
    }

    /**
     * @param JsonCoder $jsonCoder
     * @return Raml\DocNavigator
     */
    public function createNavigator(JsonCoder $jsonCoder)
    {
        return new Raml\DocNavigator($this->ramlDoc, $jsonCoder);
    }
}
