<?php
/**
 * User: czm
 */

namespace App\Model\Virtual;


use Illuminate\Support\Collection;

/**
 * 虚拟模型必须实现的接口
 * Interface DistributedContract
 * @package App\Model\Virtual
 */
interface DistributedContract
{
    /**
     * 生成或组装分布式ID的接口方法
     * @reviser czm
     * @return string
     */
    public function getDistributeId(): string;

    /**
     * 获取用于拼接分布式ID 的分隔符
     * @reviser czm
     * @return string
     */
    public function getDistributeIdSeparator(): string;

    /**
     * 从分布式ID 中分割出 块与ID
     * @reviser czm
     * @param string $distributeId
     * @return array
     */
    public static function departBlockId(string $distributeId): array;

    /**
     * 动态配置模型的分布式ID
     * @reviser czm
     * @param string $distributeId
     * @return $this
     */
    public static function setDistributeId(string $distributeId);

    /**
     * 动态配置模型的分块表
     * @reviser czm
     * @param string $distributeId
     * @param null $block
     * @return mixed
     */
    public static function setDistributeTable(string $distributeId, $block = null);

    /**
     * 配置分布式id的表于拼接块别名的映射
     * @reviser czm
     * @return array
     */
    public function distributeTableMap(): array;

    /**
     * 从批量分布式ID中块与id
     * @reviser czm
     * @param array $distributeIds
     * @return Collection
     */
    public static function getBlockAndIds(array $distributeIds): Collection;
}