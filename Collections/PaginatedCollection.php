<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\Collections;

// Collections.
use Doctrine\Common\Collections\ArrayCollection;
// ORM.
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @todo Review this design. Might not be the best ever.
 */
class PaginatedCollection extends ArrayCollection implements Paginated
{
    const ERROR_LIST_TYPE = "Can only be created from arrays and Doctrine paginators.";

    /**
     * @var Paginator
     */
    private $paginator;

    /**
     * @param Paginator|array $paginator
     */
    public function __construct($list)
    {
        if ($list instanceof Paginator) {
            $this->setPaginator($list);
            $list = $list->getIterator()->getArrayCopy();
        } elseif (!is_array($list)) {
            throw new \InvalidArgumentException(self::ERROR_LIST_TYPE);
        }

        parent::__construct($list);
    }

    /**
     * @param Paginator $paginator
     * @return self
     */
    public function setPaginator(Paginator $paginator)
    {
        $this->paginator = $paginator;

        return $this;
    }

    /**
     * @return Paginator
     */
    public function getPaginator()
    {
        return $this->paginator;
    }

    /**
     * @see Paginated::total
     */
    public function total()
    {
        return isset($this->paginator)
            ? count($this->paginator)
            : count($this);
    }

    /**
     * @param \Closure $func
     * @return self
     */
    public function map(\Closure $func)
    {
        $collection = new static(array_map($func, $this->toArray()));
        $collection->setPaginator($this->paginator);

        return $collection;
    }

    /**
     * @param \Closure $func
     * @return self
     */
    public function filter(\Closure $p)
    {
        $collection = new static(array_filter($this->toArray(), $p));
        $collection->setPaginator($this->paginator);

        return $collection;
    }
}
