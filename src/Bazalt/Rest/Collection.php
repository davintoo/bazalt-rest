<?php

namespace Bazalt\Rest;

class Collection
{
    const FILTER_TYPE_LIKE = 'like';

    const FILTER_TYPE_DATES_BETWEEN = 'dates_between';

    /**
     * @var \Bazalt\ORM\Collection
     */
    protected $collection = null;

    protected $sortableColumns = array();

    protected $filterColumns = array();

    public function __construct(\Bazalt\ORM\Collection &$collection)
    {
        $this->collection = $collection;
    }

    public function sortableBy($column, $callback = null)
    {
        $this->sortableColumns[$column] = $callback === null ? false : $callback;

        return $this;
    }

    public function filterBy($column, $callback = null)
    {
        $this->filterColumns[$column] = $callback === null ? false : $callback;

        return $this;
    }

    public function exec($params = array())
    {
        if (!isset($params['page'])) {
            $params['page'] = 1;
        }
        if (!isset($params['count'])) {
            $params['count'] = 10;
        }

        // filter
        if (isset($params['filter'])) {
            foreach ($params['filter'] as $columnName => $value) {
                if (!isset($this->filterColumns[$columnName])) {
                    continue;
                }
                if(is_string($value)) {
                    $value = urldecode($value);
                }
                if ($this->filterColumns[$columnName] !== false && is_callable($this->filterColumns[$columnName])) {
                    $callback = $this->filterColumns[$columnName];
                    $callback($this->collection, $columnName, $value);
                } else if ($this->filterColumns[$columnName] == self::FILTER_TYPE_LIKE) {
                    if($value) {
                        $value = '%' . strtolower($value) . '%';
                        $this->collection->andWhere('LOWER(`' . $columnName . '`) LIKE ?', $value);
                    }
                } else if ($this->filterColumns[$columnName] == self::FILTER_TYPE_DATES_BETWEEN) {
                    if(isset($params[$columnName]) && is_array($params[$columnName])) {
                        $this->collection->andWhere('DATE(`' . $columnName . '`) BETWEEN ? AND ?', $params[$columnName]);
                    }
                } else {
                    $this->collection->andWhere('`' . $columnName . '` = ?', $value);
                }
            }
        }

        // sorting
        if (isset($params['sorting'])) {
            $this->collection->clearOrderBy();
            foreach ($params['sorting'] as $key => $item) {
                $firstLetter = $item[0];
                if ($firstLetter == '-' || $firstLetter == '+') {
                    $direction = $item[0] == '+' ? 'ASC' : 'DESC';
                    $columnName = substr($item, 1);
                } else {
                    $direction = strtolower($item) == 'asc' ? 'ASC' : 'DESC';
                    $columnName = $key;
                }
                if (!isset($this->sortableColumns[$columnName])) {
                    continue;
                }
                if ($this->sortableColumns[$columnName] !== false && is_callable($this->sortableColumns[$columnName])) {
                    $callback = $this->sortableColumns[$columnName];
                    $callback($this->collection, $columnName, $direction);
                } else {
                    $this->collection->addOrderBy('`' . $columnName . '` ' . $direction);
                }
            }
        }
//        echo $this->collection->toSql()."\n";
        $this->collection->page((int)$params['page']);
        $this->collection->countPerPage((int)$params['count']);
    }

    public function fetch($params = array(), $callback = null, $className = null)
    {
        $this->exec($params);

        $return = array();
        try {
            $result = $this->collection->fetchPage($className);
        } catch(\Bazalt\ORM\Exception\Collection $ex) {//Invalid page
            $this->collection->page(1);
            $result = $this->collection->fetchPage($className);
        }


        foreach ($result as $k => $item) {
            if ($callback && is_callable($callback)) {
                $res = $item->toArray();
                $res = $callback($res, $item);
            } else if ($item instanceof \stdClass) {
                $res = (array)$item;
            } else {
                $res = $item->toArray();
            }

            // filter fields
            if (isset($params['fields'])) {
                $fields = array_flip(explode(',', $params['fields']));
                $res = array_intersect_key($res, $fields);
            }
            $return[$k] = $res;
        }

        $data = array(
            'data' => $return,
            'pager' => array(
                'current'       => (int)$this->collection->page(),
                'count'         => (int)$this->collection->getPagesCount(),
                'total'         => (int)$this->collection->count(),
                'countPerPage'  => (int)$this->collection->countPerPage()
            )
        );
        //$data['sql'] = $this->collection->toSQL();
        return $data;
    }
}
