<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\JsonApi\Request;

// HTTP.
use Symfony\Component\HttpFoundation\Request;
// JSON.
use GoIntegro\Bundle\HateoasBundle\Util\JsonCoder;

/**
 * @see http://jsonapi.org/format/#crud-updating
 */
class UpdateBodyParser
{
    const ERROR_MISSING_ID = "A data set provided is missing the Id.",
        ERROR_DUPLICATED_ID = "The Id \"%s\" was sent twice.";

    /**
     * @var JsonCoder
     */
    protected $jsonCoder;

    /**
     * @param JsonCoder $jsonCoder
     */
    public function __construct(JsonCoder $jsonCoder)
    {
        $this->jsonCoder = $jsonCoder;
    }

    /**
     * @param Request $request
     * @param Params $params
     * @return array
     */
    public function parse(Request $request, Params $params)
    {
        $rawBody = $request->getContent();
        $data = $this->jsonCoder->decode($rawBody);
        $entityData = [];

        if (empty($data[$params->primaryType])) {
            throw new ParseException(BodyParser::ERROR_PRIMARY_TYPE_KEY);
        } elseif (isset($data[$params->primaryType]['id'])) {
            $id = $data[$params->primaryType]['id'];

            if (isset($entityData[$id])) {
                $message = sprintf(static::ERROR_DUPLICATED_ID, $id);
                throw new ParseException($message);
            } else {
                $entityData[$id] = $data[$params->primaryType];
            }
        } else {
            foreach ($data[$params->primaryType] as $datum) {
                if (!isset($datum['id'])) {
                    throw new ParseException(static::ERROR_MISSING_ID);
                } else {
                    $entityData[$datum['id']] = $datum;
                }
            }
        }

        return $entityData;
    }
}
