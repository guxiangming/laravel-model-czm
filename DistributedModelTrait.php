<?php
/**
 * User: czm
 */

namespace App\Model\Virtual;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * 分布式表的常用特性
 * SomeTrait DistributedModelTrait
 * @package App\Model
 */
trait DistributedModelTrait
{
    /**
     * 数据表水平分表后要用uuid
     * @reviser czm
     * @return $this
     * @throws \Exception
     */
    public function decorateID(): self
    {
        return $this->setKeyType('string')->forceFill([
            'ID' => $this->getDistributeId()
        ]);
    }

    /**
     * 组装分布式ID
     * @reviser czm
     * @return string example 100/tableAlias1
     */
    public function getDistributeId(): string
    {
        $map    = array_flip($this->distributeTableMap());
        $prefix = $map[$this->getTable()] ?? $this->getTable();
        return $this->original['ID'] . $this->getDistributeIdSeparator() . $prefix;
    }

    public function getDistributeIdSeparator(): string
    {
        return $this->distributeIdSeparator ?? '-';
    }

    /**
     * @reviser czm
     * @param $distributeId
     * @return array
     */
    public static function departBlockId(string $distributeId): array
    {
        return array_combine(['id', 'block'], array_slice(
            explode((new static())->getDistributeIdSeparator(), $distributeId), 0, 2
        ));
    }

    /**
     * 获取单个分布式模型，要配置表，配置ID
     * @reviser czm
     * @param string $distributeId
     * @return $this
     */
    public static function setDistributeId(string $distributeId)
    {
        $id = static::departBlockId($distributeId)['id'];
        return static::setDistributeTable($distributeId)->where('ID', $id);
    }

    /**
     * 模型动态设置分布式表
     * @reviser czm
     * @lastModify 2019/6/26 14:56
     * @param $distributeId
     * @param $block
     * @return $this
     */
    public static function setDistributeTable(string $distributeId, $block = null)
    {
        $block || $block = static::departBlockId($distributeId)['block'];
        $model = (new static());
        return $model->setTable($model->distributeTableMap()[$block] ?? $block);
    }

    /**
     * @reviser czm
     * @param array $attributes
     * @param bool $exists
     * @return $this
     * @throws \Exception
     */
    public function newInstance($attributes = [], $exists = false)
    {
        return parent::newInstance($attributes, $exists)->setTable($this->getTable());
    }

    /**
     * @reviser czm
     * @return mixed
     * @throws \Exception
     */
    public function toArray()
    {
        $this->decorateID();
        return parent::toArray();
    }

    public function distributeTableMap()
    {
        return [];
    }

    

    /**
     * @reviser czm
     * @param array $distributeIds
     * @return Collection
     */
    public static function getBlockAndIds(array $distributeIds): Collection
    {
        return collect($distributeIds)->map(function ($distributeId) {
            return static::departBlockId($distributeId);
        });
    }
}