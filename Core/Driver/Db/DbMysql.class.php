<?php
/**
 * MySQL数据库驱动类
 * @author Yurun <admin@yurunsoft.com>
 */
class DbMysql extends DbBase
{
	// 参数标识
	protected $param_flag = array ('`','`');
	private $fp;
	/**
	 * 连接数据库
	 */
	public function connect($config = null)
	{
		if (empty($config))
		{
			$config = $this->config;
		}
		else
		{
			$this->config = $config;
		}
		if (empty($this->conn))
		{
			// 连接信息
			$server = (isset($config['host']) ? $config['host'] : 'localhost') . ':' . (isset($config['port']) && is_numeric($config['port']) ? $config['port'] : '3306');
			$username = isset($config['username']) ? $config['username'] : 'root';
			$password = isset($config['password']) ? $config['password'] : '';
			$flags = ((isset($config['flags']) && is_numeric($config['flags'])) ? $config['flags'] : 0);
			// 连接
			if (isset($config['pc_connect']) && $config['pc_connect'])
			{
				$this->conn = mysql_pconnect($server, $username, $password, $flags);
			}
			else
			{
				$this->conn = mysql_connect($server, $username, $password, ((isset($config['newlink']) && is_bool($config['newlink'])) ? $config['newlink'] : 1), $flags);
			}
			if (false !== $this->conn)
			{
				// 设置编码
				if (isset($config['charset']))
				{
					$this->execute("set names {$config['charset']}");
				}
				// 选择数据库
				if (isset($config['dbname']))
				{
					$this->selectDb($config['dbname']);
				}
				$this->connect = true;
				return true;
			}
			else
			{
				$this->connect = false;
				return false;
			}
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * 断开数据库连接
	 */
	public function disConnect()
	{
		if ($this->free() && mysql_close($this->conn))
		{
			$this->conn = null;
			$this->connect = false;
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * 选择数据库
	 *
	 * @param
	 *        	string 数据库名
	 */
	public function selectDb($dbName)
	{
		return mysql_select_db($dbName);
	}
	
	/**
	 * 释放结果集
	 */
	public function free()
	{
		if (null !== $this->result && ! is_bool($this->result))
		{
			mysql_free_result($this->result);
			$this->result = null;
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * 查询一条记录
	 *
	 * @param string $sql        	
	 */
	public function query($sql)
	{
		if ($this->execute($sql))
		{
			if (is_bool($this->result))
			{
				return $this->result;
			}
			else
			{
				$result = mysql_fetch_array($this->result);
				if (false !== $result)
				{
					return $result;
				}
				else
				{
					return $result;
				}
			}
		}
		else
		{
			return array ();
		}
	}
	
	/**
	 * 查询多条记录
	 *
	 * @param string $sql        	
	 */
	public function queryA($sql)
	{
		if ($this->execute($sql))
		{
			if (is_bool($this->result))
			{
				return $this->result;
			}
			else
			{
				$result = array ();
				while ($t = mysql_fetch_array($this->result))
				{
					$result[] = $t;
				}
				return $result;
			}
		}
		else
		{
			return array ();
		}
	}
	
	/**
	 * 执行SQL语句
	 *
	 * @param string $sql        	
	 */
	public function execute($sql)
	{
		// 解决执行存储过程后再执行语句就出错
		if ('call ' == substr($this->lastSql, 0, 5))
		{
			$this->disconnect();
			$this->connect();
		}
		// 记录最后执行的SQL语句
		$this->lastSql = $sql;
		// 执行SQL语句
		$this->result = mysql_query($sql, $this->conn);
		if(false===$this->result)
		{
			// 用于调试
			$GLOBALS['debug']['lastsql']=$this->lastSql;
			throw new Exception($this->getError());
		}
		return false !== $this->result ? true : false;
	}
	
	/**
	 * 执行存储过程
	 *
	 * @access public
	 * @param
	 *        	string procName 存储过程名称
	 * @return array
	 */
	public function execProc($procName)
	{
		$p = func_get_args();
		if (isset($p[1]) && is_array($p[1]))
		{
			return $this->queryA("call $procName(" . $this->filterValue($p[1]) . ')');
		}
		else
		{
			return $this->queryA("call $procName(" . $this->filterValue($p) . ')');
		}
	}
	
	/**
	 * 执行数据库函数
	 *
	 * @access public
	 * @param
	 *        	string procName 函数名称
	 * @return array
	 */
	public function execFunction($funName)
	{
		$p = func_get_args();
		unset($p[0]);
		if (count($p) === 1 && is_array($p[0]))
		{
			return $this->queryValue("select $procName(" . $this->filterValue($p[0]) . ')');
		}
		else
		{
			return $this->queryValue("select $procName(" . $this->filterValue($p) . ')');
		}
	}
	
	/**
	 * 获取结果行数
	 *
	 * @access public
	 * @return int
	 */
	public function foundRows()
	{
		return mysql_num_rows($this->result);
	}
	
	/**
	 * 获取影响行数
	 *
	 * @access public
	 * @return int
	 */
	public function rowCount()
	{
		return mysql_affected_rows($this->conn);
	}
	
	/**
	 * 最后insert自动编号的ID
	 *
	 * @access public
	 * @return int
	 */
	public function lastInsertID()
	{
		return mysql_insert_id($this->conn);
	}
	
	/**
	 * 获取最后一条错误信息
	 */
	public function getError()
	{
		if($this->connect)
		{
			$error = iconv('GBK', 'UTF-8//IGNORE', mysql_error($this->conn));
			if ('' !== $error)
			{
				$error .= '错误代码：' . mysql_errno($this->conn) . (empty($this->lastSql)?'':" SQL语句:{$this->lastSql}");
			}
		}
		else
		{
			$error = iconv('GBK', 'UTF-8//IGNORE', mysql_error());
			if ('' !== $error)
			{
				$error .= '错误代码：' . mysql_errno() . (empty($this->lastSql)?'':" SQL语句:{$this->lastSql}");
			}
		}
		return $error;
	}
	
	/**
	 * 获取数据库中所有数据表名
	 *
	 * @param string $dbname        	
	 */
	public function getTables($dbName = null)
	{
		if (empty($dbName))
		{ // 当前表
			$sql = 'show tables';
		}
		else
		{ // 其他表
			$sql = 'show tables from ' . $this->parseField($dbName);
		}
		// 查询
		$result = $this->queryA($sql);
		if (false === $result)
		{ // 失败
			return false;
		}
		else
		{
			$r = array ();
			// 处理数据
			foreach ($result as $value)
			{
				$r[] = $value[0];
			}
			// 返回结果
			return $r;
		}
	}
	
	/**
	 * 获取数据表中所有字段详细信息
	 *
	 * @param string $table        	
	 */
	public function getFields($table)
	{
		// 查询
		$result = $this->queryA('show columns from ' . $this->parseField($table));
		if (false === $result)
		{ // 失败
			return false;
		}
		else
		{
			$r = array ();
			// 处理数据
			foreach ($result as $value)
			{
				$r[] = array ('name' => $value['Field'],'type' => $value['Type'],'null' => 'yes' === strtolower($value['Null']),'default' => $value['Default'],'key' => $value['Key'],'autoinc' => strtolower($value['Extra']) === 'auto_increment','ex' => $value['Extra']);
			}
			// 返回结果
			return $r;
		}
	}
	/**
	 * 开始事务
	 */
	public function begin()
	{
		$this->execute('begin');
	}
	/**
	 * 回滚事务
	 */
	public function rollback()
	{
		$this->execute('rollback');
	}
	/**
	 * 提交事务
	 */
	public function commit()
	{
		$this->execute('commit');
	}
	/**
	 * 解析sql文件，支持返回sql数组，或者使用回调函数
	 * @param string $file
	 * @param callback $callback
	 * @return mixed
	 */
	public function parseMultiSql($file,$callback=null)
	{
		$this->fp = fopen($file, 'r');
		if(false===$this->fp)
		{
			return false;
		}
		else 
		{
			if(empty($callback))
			{
				$result=array();
			}
			$sql='';
			while ($line = fgets($this->fp, 40960))
			{
				$line=trim($line);
				if (isset($line[1]))
				{
					if ('#'===$line[0] || ('-'===$line[0] && '-'===$line[1]))
					{
						continue;
					}
				}
				$sql.="{$line}\r\n";
				if (isset($line[0]))
				{
					if (';'===substr($line,0,-1))
					{
						$sql=trim(preg_replace("'/\*[^*]*\*/'", '', $sql));
						if(empty($callback))
						{
							$result[]=$sql;
						}
						else 
						{
							call_user_func($callback,$sql);
						}
						$sql='';
					}
				}
			}
			if(empty($callback))
			{
				return $result;
			}
		}
	}
}