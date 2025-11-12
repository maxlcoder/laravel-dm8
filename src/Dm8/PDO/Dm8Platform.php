<?php

namespace LaravelDm8\Dm8\PDO;

use Doctrine\DBAL\Platforms\OraclePlatform;

/**
 * Dm8Platform.
 *
 * The Dm8Platform class provides the platform-specific SQL generation
 * and database feature abstraction for DM8 database.
 *
 * @author DM8 Support Team
 */
class Dm8Platform extends OraclePlatform
{
    /**
     * {@inheritDoc}
     */ 
    public function getName()
    {
        return 'dm8';
    }

    /**
     * Override to return empty string, so identifiers are not quoted.
     */
    public function getIdentifierQuoteCharacter()
    {
        return '';
    }

    /**
     * Register a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @param string $dbType
     * @param string $doctrineType
     *
     * @return void
     */
    public function registerDoctrineTypeMapping($dbType, $doctrineType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeDoctrineTypeMappings();
        }

        $this->doctrineTypeMapping[$dbType] = $doctrineType;
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = [
            // === 数值类型 ===
            'tinyint'       => 'smallint',     // 达梦的TINYINT映射为smallint
            'smallint'      => 'smallint',     // 短整型
            'int'           => 'integer',      // 整型
            'integer'       => 'integer',      // 整型
            'bigint'        => 'bigint',       // 长整型
            'number'        => 'integer',      // NUMBER默认映射为integer
            
            // NUMBER带精度的情况（需要特殊处理）
            'numeric'       => 'decimal',      // 精确数值
            'decimal'       => 'decimal',      // 精确数值
            'real'          => 'float',        // 单精度浮点
            'float'         => 'float',        // 双精度浮点
            'double'        => 'float',        // 双精度浮点
            'binary_float'  => 'float',       // 二进制浮点
            'binary_double' => 'float',        // 二进制双精度
            
            // === 字符类型 ===
            'char'          => 'string',       // 定长字符串
            'character'     => 'string',       // 定长字符串
            'varchar'       => 'string',       // 变长字符串
            'varchar2'      => 'string',       // 变长字符串（Oracle兼容）
            'nvarchar'      => 'string',       // 国家字符集变长
            'nvarchar2'     => 'string',       // 国家字符集变长
            'longvarchar'   => 'text',         // 长字符串
            
            // === 文本类型 ===
            'clob'          => 'text',         // 字符大对象
            'text'          => 'text',         // 文本类型
            'long'          => 'text',         // 长文本
            
            // === 二进制类型 ===
            'binary'        => 'blob',         // 定长二进制
            'varbinary'     => 'blob',         // 变长二进制
            'longvarbinary'=> 'blob',         // 长二进制
            'blob'          => 'blob',         // 二进制大对象
            'raw'           => 'binary',      // 原始二进制
            'long raw'      => 'blob',         // 长原始二进制
            'bfile'         => 'blob',         // 外部二进制文件
            
            // === 日期时间类型 ===
            'date'          => 'date',         // 日期
            'time'          => 'time',         // 时间
            'timestamp'     => 'datetime',     // 时间戳
            'datetime'      => 'datetime',     // 日期时间
            
            // === 布尔类型 ===
            'boolean'       => 'boolean',      // 布尔类型
            
            // === 其他类型 ===
            'rowid'         => 'string',       // 行ID
            'urowid'        => 'string',       // 通用行ID
            'xml'           => 'string',       // XML类型
            'json'          => 'json',         // JSON类型（Doctrine 2.10+）
            
            // === 达梦特有类型 ===
            'image'         => 'blob',         // 图像类型
            
            // PL/SQL相关类型（映射到最接近的Doctrine类型）
            'pls_integer'   => 'integer',      // PL/SQL整数（不是boolean！）
            'binary_integer'=> 'integer',      // 二进制整数（不是boolean！）
            
            // 区间类型
            'interval year'  => 'string',      // 年间隔
            'interval day'   => 'string',      // 日间隔
        ];
    }
}
