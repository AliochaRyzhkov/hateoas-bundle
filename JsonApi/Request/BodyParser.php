<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\JsonApi\Request;

// HTTP.
use Symfony\Component\HttpFoundation\Request,
    GoIntegro\Bundle\HateoasBundle\Http\Url;
// Recursos.
use GoIntegro\Bundle\HateoasBundle\JsonApi\DocumentPagination;
// JSON.
use GoIntegro\Bundle\HateoasBundle\Util\JsonCoder;
// RAML.
use GoIntegro\Bundle\HateoasBundle\Raml\DocFinder;

/**
 * @see http://jsonapi.org/format/#crud
 */
class BodyParser
{
    /**
     * @param JsonCoder $jsonCoder
     * @param DocFinder $docFinder
     */
    public function __construct(JsonCoder $jsonCoder, DocFinder $docFinder)
    {
        $this->updateBodyParser = new UpdateBodyParser($jsonCoder, $docFinder);
    }

    /**
     * @param Request $request
     * @param Params $params
     * @return array
     */
    public function parse(Request $request, Params $params)
    {
        switch ($request->getMethod()) {
            case 'POST':
                break;

            case 'PUT':
                return $this->updateBodyParser->parse($request, $params);
                break;

            default:
                throw new \Exception;
        }
    }
}
