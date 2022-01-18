<?php

namespace Bolt;

class DbSet extends DbTable
{

    private $_context;

	private string $_columns = '*';
	private array $_where = [];
	private array $_having = [];
	private string $_groupBy = '';
	private string $_orderBy = '';
	private int $_quantity = 10;
	private int $_page = 1;
	private array $_params = [];
	private int $_totalCount = 0;

    public function __construct(string $name, Db $context)
    {
        $this->_name = $name;
        $this->_context = $context;
    }

    public function quantity(int $q): DbSet
    {
        $this->_quantity = $q;
        return $this;
    }

    public function page(int $p): DbSet
    {
        $this->_page = $p;
        return $this;
    }

    public function withPaging(): DbSet
    {
        $this->_totalCount = 0;
        return $this;
    }

    private function _reset(): void
    {
        $this->_columns = '*';
        $this->_where = [];
        $this->_having = [];
        $this->_groupBy = '';
        $this->_orderBy = '';
        $this->_quantity = 10;
        $this->_page = 1;
        $this->_params = [];
    }

    public function add(array $record): DbModel
    {
        return new DbModel($this->_name, $record, $this->_context);
    }

    public function where(): DbSet
    {
        $args = func_get_args();
        $numArgs = func_num_args();
        switch ($numArgs) {
            case 0:{
                    return $this;
                }case 1:{
                    $this->_where[] = $args[0];
                    break;
                }case 2:{
                    $this->_where[] = "`$args[0]` = :$args[0]";
                    $this->_params[":$args[0]"] = $args[1];
                    break;
                }default:{
                    if (strtolower(trim($args[1])) == 'in') {
                        $ins = [];
                        $inArgs = is_array($args[2]) ? $args[2] : array_slice($args, 2);
                        foreach ($inArgs as $i => $val) {
                            $ins[] = ":{$args[0]}In$i";
                            $this->_params[":{$args[0]}In$i"] = $val;
                        }
                        $this->_where[] = "`$args[0]` IN ( " . implode(', ', $ins) . ' )';
                    } else if (strtolower(trim($args[1])) == 'between') {
                        if ($numArgs != 4) {
                            throw new Exception('Between operator requires two operands!');
                        }
                        $this->_params[":{$args[0]}Between1"] = $args[2];
                        $this->_params[":{$args[0]}Between2"] = $args[3];
                        $this->_where[] = "`$args[0]` BETWEEN :{$args[0]}Between1 AND :{$args[0]}Between2";
                    } else {
                        $this->_where[] = "`$args[0]` $args[1] :$args[0]";
                        $this->_params[":$args[0]"] = $args[2];
                    }
                    break;
                }
        }
        return $this;
    }

    public function having(): DbSet
    {
        $args = func_get_args();
        $numArgs = func_num_args();
        switch ($numArgs) {
            case 0:{
                    return $this;
                }case 1:{
                    $this->_having[] = $args[0];
                    break;
                }case 2:{
                    $this->_having[] = "`$args[0]` = :$args[0]";
                    $this->_params[":$args[0]"] = $args[1];
                    break;
                }default:{
                    if (strtolower(trim($args[1])) == 'in') {
                        $ins = [];
                        $inArgs = is_array($args[2]) ? $args[2] : array_slice($args, 2);
                        foreach ($inArgs as $i => $val) {
                            $ins[] = ":{$args[0]}In$i";
                            $this->_params[":{$args[0]}In$i"] = $val;
                        }
                        $this->_having[] = "`$args[0]` IN ( " . implode(', ', $ins) . ' )';
                    } else if (strtolower(trim($args[1])) == 'between') {
                        if ($numArgs != 4) {
                            throw new Exception('Between operator requires two operands!');
                        }
                        $this->_params[":{$args[0]}Between1"] = $args[2];
                        $this->_params[":{$args[0]}Between2"] = $args[3];
                        $this->_having[] = "`$args[0]` BETWEEN :{$args[0]}Between1 AND :{$args[0]}Between2";
                    } else {
                        $this->_having[] = "`$args[0]` $args[1] :$args[0]";
                        $this->_params[":$args[0]"] = $args[2];
                    }
                    break;
                }
        }
        return $this;
    }

    public function andHaving(): DbSet
    {
        return call_user_func_array([$this, 'having'], func_get_args());
    }

    public function andWhere(): DbSet
    {
        return call_user_func_array([$this, 'where'], func_get_args());
    }

    public function select(array | string $columns): DbSet
    {
        $this->_columns = is_array($columns) ? '`' . implode('`, `', $columns) . '`' : $columns;
        return $this;
    }

    public function orderBy(string $order): DbSet
    {
        $this->_orderBy = $order;
        return $this;
    }

    public function groupBy(string $group): DbSet
    {
        $this->_groupBy = $group;
        return $this;
    }

    public function count($reset = true): int
    {
        $tmp = $this->_columns;
        $tmpPage = $this->_page;
        $count = $this->select('COUNT( * ) AS `cnt`')->page(1)->fetch($reset)->first()->cnt;
        $this->_columns = $tmp;
        $this->_page = $tmpPage;
        return $count;
    }

    public function fetch(bool $reset = true): DbResult
    {
        if ($this->_totalCount === 0) {
            $this->_totalCount = null;
            $this->_totalCount = $this->count(false);
        }
        $result = $this->trigger('beforeSelect', $this, $this->_name);
        if ($result === false) {
            return new DbResult($this->_name, [], $this->_context, 0, 0, 1);
        }
        $where = implode(' AND ', $this->_where);
        $table = $this->getTableName();
        $sql = "SELECT {$this->_columns} FROM `{$table}`";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        if ($this->_groupBy) {
            $sql .= ' GROUP BY ' . $this->_groupBy;
        }
        $having = implode(' AND ', $this->_having);
        if ($having) {
            $sql .= " HAVING {$having}";
        }
        if ($this->_orderBy) {
            $sql .= ' ORDER BY ' . $this->_orderBy;
        }
        $sql .= '  LIMIT ' . $this->_limit();

        $result = $this->_context->select($sql, $this->_params, $this->_name, $this->_totalCount, $this->_quantity, $this->_page);
        $reset && $this->_reset();
        return $result;
    }

    private function _limit(): string
    {
        return (($this->_page - 1) * $this->_quantity) . ', ' . $this->_quantity;
    }

    public function first(): DbModel
    {
        $this->_limit = '0, 1';
        $result = $this->fetch();
        return $result->count() > 0 ? $result[0] : null;
    }

    public function toArray()
    {
        return $this->fetch()->toArray();
    }
}
