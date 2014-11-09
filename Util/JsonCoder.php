<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\Util;

// JSON.
use JsonSchema\Validator;

/**
 * La fachada del servicio de validación de JSON schemas.
 *
 * Not using the herrera-io/json lib because validation code should not
 * throw exceptions.
 */
class JsonCoder
{
    const FAIL_JSON_SCHEMA_MESSAGE = "Failed asserting that the JSON matches the given schema. Violations:\n",
        JSON_API_SCHEMA_PATH = '/../Resources/json-schemas/json-api-schema.json';

    /**
     * @var array
     */
    private static $errorMessages = [
        JSON_ERROR_NONE => "No error has occurred.",
        JSON_ERROR_DEPTH => "The maximum stack depth has been exceeded.",
        JSON_ERROR_STATE_MISMATCH => "Invalid or malformed JSON.",
        JSON_ERROR_CTRL_CHAR => "Control character error, possibly incorrectly encoded.",
        JSON_ERROR_SYNTAX => "Syntax error.",
        JSON_ERROR_UTF8 => "Malformed UTF-8 characters, possibly incorrectly encoded.",
        JSON_ERROR_RECURSION => "One or more recursive references in the value to be encoded.",
        JSON_ERROR_INF_OR_NAN => "One or more NAN or INF values in the value to be encoded.",
        JSON_ERROR_UNSUPPORTED_TYPE => "A value of a type that cannot be encoded was given."
    ];

    /**
     * @var array
     */
    private $lastSchemaErrors = [];

    /**
     * Codifica el parámetro a JSON.
     * @param mixed $value
     * @return string
     */
    public function encode($value)
    {
        $json = json_encode($value);

        if ($code = json_last_error()) $this->throwError($code);

        return $json;
    }

    /**
     * Codifica el parámetro a JSON.
     * @param string $json
     * @param boolean $toObject
     * @return array
     */
    public function decode($json, $toObject = FALSE)
    {
        $value = json_decode($json, !$toObject);

        if ($code = json_last_error()) $this->throwError($code);

        return $value;
    }

    /**
     * @param integer $code
     * @throws \ErrorException
     */
    private function throwError($code)
    {
        $message = isset(self::$errorMessages[$code])
            ? self::$errorMessages[$code]
            : "Unknown error.";

        throw new \ErrorException($message);
    }

    /**
     * Matches a JSON string or structure to a schema.
     *
     * It would be lovely to be able to return an object containing both
     * the result and the errors wich would itself be castable to boolean,
     * but alas, this is not yet possible on PHP.
     * @param string $json
     * @param string $schema
     * @return boolean
     * @see http://json-schema.org/
     */
    public function matchSchema($json, $schema)
    {
        $validator = new Validator();

        if (is_string($json)) {
            $json = $this->decode($json, TRUE);
        }

        if (is_file($schema) && is_readable($schema)) {
            $schema = file_get_contents($schema);
        }

        if (is_string($schema)) {
            $schema = $this->decode($schema, TRUE);
        }

        $validator->check($json, $schema);
        $this->lastSchemaErrors = $validator->getErrors();

        return $validator->isValid();
    }

    /**
     * @param string $json
     * @return boolean
     */
    public function assertJsonApi($json)
    {
        $schema = __DIR__ . self::JSON_API_SCHEMA_PATH;

        return $this->matchSchema($json, $schema);
    }

    /**
     * Returns the latest schema matching errors.
     * @return array
     * @see http://php.net/manual/en/function.json-last-error.php
     */
    public function getSchemaErrors()
    {
        return $this->lastSchemaErrors;
    }

    /**
     * Returns the latest schema matching errors as a text message.
     * @return string
     * @see http://php.net/manual/en/function.json-last-error-msg.php
     */
    public function getSchemaErrorMessage()
    {
        $message = NULL;

        foreach ($this->lastSchemaErrors as $error) {
            $message .= sprintf(
                "[%s] %s\n", $error['property'], $error['message']
            );
        }

        if (!empty($message)) {
            $message = self::FAIL_JSON_SCHEMA_MESSAGE . $message;
        }

        return $message;
    }
}
