<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class virtualMoel extends Model
{
    use DistributedModelTrait, 

    protected $connection = '';
    protected $table = '';
    protected $dates = [
        'VehicleDispactionTime', 'DriverAcceptOrderTime', 'DriverStartUpTime', 'ReportContanierTime',
        'BackElectronicDucumentTime', 'CompleteDeliveryTime', 'ActualLoadingDateTime', 'ActualLoadingDateTime'
    ];
    protected $loadTable = 'hlyun_order_dynamic_trailer_load';
    protected $unloadTable = 'hlyun_order_dynamic_trailer_unload';
    protected $appends = ['OrderType'];


    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('VehicleDispactionNamber', function(Builder $builder) {
            $builder->where('VehicleDispactionNamber', 'not like', "WP_%");
        });
    }

    /**
     * @reviser czm
     * @param string $table
     * @return Model
     * @throws \Exception
     */
    public function setTable($table)
    {
        if ($table != $this->loadTable && $table !== $this->unloadTable) {
            throw new \Exception('您正常尝试进行错误的动态模型初始化');
        }
        return parent::setTable($table);
    }

    /**
     * 订单作用域
     * @reviser czm
     * @param $query
     * @param string $term
     * @return mixed
     */
    public function scopeOfDateSort($query, $term = 'any')
    {
        return $query->whereHas('containers.order.orderLines', function ($query) use ($term) {
            $query->ofPlannedDay($term);
        });
    }

    /**
     * 有该运单号的作用域
     * @reviser czm
     * @param $query
     * @param $waybillNum
     */
    public function scopeOfWaybillNum($query, $waybillNum)
    {
        $waybillNum && $query->whereHas('containers.seaShip', function ($query) use ($waybillNum) {
            $query->where('TransportDocumentNumber', 'like', '%' . $waybillNum . '%');
        });
    }

    /**
     * 柜号作用域
     * @reviser czm
     * @param $query
     * @param $number
     * @return mixed
     */
    public function scopeOfContainerNumber($query, $number)
    {
        return $number ? $query->whereHas('containers', function ($query) use ($number) {
            $query->where('ContainerNumber', 'like', '%' . $number . '%');
        }) : $query;
    }

    /**
     * 拖车派车司机作用域
     * @reviser czm
     * @param $query
     * @param array $userInfo
     * @return mixed
     */
    public function scopeOfDriver($query, array $userInfo = [])
    {
        $userInfo || $userInfo = (AuthTokenClass::decryptSsoToken());
        $tableName = $this->getTable();
        // $query->where($tableName . '.DriverName', $userInfo['Name']);//司机端因不同企业，新建同手机号不同名字的账号容易搜索失效，建议注释点
        
        $query->where($tableName . '.DriverIDCardNumber', $userInfo['DriverIDCardNumber']);

        isset($userInfo['AccreditId']) && $query->where($tableName . '.TrailerCompanyId', $userInfo['AccreditId']);
        return $query;
    }

    /**
     * 派车单号作用域
     * @reviser czm
     * @param $query
     * @param $number
     * @return mixed
     */
    public function scopeOfOrderNumber($query, $number)
    {
        return $number ? $query->where('VehicleDispactionNamber', 'like', '%' . $number . '%') : $query;
    }

    /**
     * @reviser czm
     * @param $query
     * @param bool $bool
     */
    public function scopeOfAccept($query, bool $bool = true)
    {
        $bool
            ? $query->where(function ($query) {
            $query->whereNotNull('DriverAcceptOrderTime')->orWhere('DriverAcceptOrderTime', '>', '00001');
        })
            : $query->where(function ($query) {
            $query->whereNull('DriverAcceptOrderTime')->orWhere('DriverAcceptOrderTime', '<', '00001');
        });
    }

    /**
     * @reviser czm
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * @throws \Exception
     */
    public function containers()
    {
        if ($this->getTable() == $this->loadTable) {
            return $this->containerLoads();
        } else {
            return $this->containerUnloads();
        }
    }

    /**
     * 保护起来是因为不能直接使用，会有小问题
     * @reviser czm
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    protected function containerLoads()
    {
        return $this->belongsToMany(
            hlyun_order_containers::class,
            'hlyun_order_index_container_dynamics',
            'DynamicTrailerLoadId',
            'ContainerId'
        );
    }

    /**
     * 保护起来是因为不能直接使用，会有小问题
     * @reviser czm
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    protected function containerUnloads()
    {
        return $this->belongsToMany(
            hlyun_order_containers::class,
            'hlyun_order_index_container_dynamics',
            'DynamicTrailerUnloadId',
            'ContainerId'
        );
    }

    /**
     * 判断是否装货拖车
     * @reviser czm
     * @return bool
     * @throws \Exception
     */
    public function isLoad()
    {
        if ($this->getTable() == $this->loadTable) {
            return true;
        } elseif ($this->getTable() == $this->unloadTable) {
            return false;
        } else {
            throw new \Exception('不指定表就不能用关联关系');
        }
    }

    /**
     * 通过柜找派单信息，注意是system=1拖车派单
     * @reviser czm
     * @return mixed
     * @throws \Exception
     */
    public function getDispatchOrdersAttribute()
    {
        $orderType = $this->OrderType;
        return $this->containers->pluck('dispatchOrder')->collapse()
            ->where('OrderType', $orderType)
            ->where('System', 1)
            ->unique('ID');
    }

    /**
     * 通过柜找船运信息
     * @reviser czm
     * @return mixed
     */
    public function getSeaShipAttribute()
    {
        return $this->containers->pluck('seaShip')->collapse()->unique();
    }

    /**
     * 通过柜找费用记录
     * @reviser czm
     * @return mixed
     */
    public function getCostsAttribute()
    {
        return $this->containers->pluck('payCosts')
            ->collapse()->unique('ID')->filter(function ($item) {
                $Type = isset(\DB::connection('hlyun_srm')->table('hlyun_capacity_vehicles')->where('CarType',0)->where('VehicleNumber',$this->VehicleNumber)->first()->ID)?1:0;
                // return $item->attributes['AccountingObjectName'] == $this->attributes['TrailerCompanyName'];
                return $item->attributes['CostItemName'] == '始驳费' && $Type == 1
                    || $item->attributes['CostItemName'] == '达驳费' && $Type == 1;
            });
    }

    /**
     * 拖车费用
     * @reviser czm
     * @param $value
     * @return mixed
     */
    public function getTrailerPriceAttribute($value)
    {
        if ($value) {
            return $value;
        }
        return $this->costs->sum('TotalAmount');
    }

    /**
     * 填充拖车价格
     * @reviser czm
     * @return $this
     */
    public function fillTrailerPrice()
    {
        return $this->forceFill(['TrailerPrice' => $this->costs->sum('TotalAmount')]);
    }

    /**
     * 填充拖车代收款
     * @reviser czm
     * @return $this
     */
    public function getReplaceChargeCostAttribute()
    {
        return $this->containers->pluck('recCosts')
            ->collapse()->unique('ID')->filter(function ($item) {
                return $item->attributes['CostItemName'] == '代收款';
            })->sum('TotalAmount');
    }

    /**
     * 获取柜号录入已否
     * @reviser czm
     * @return bool
     */
    public function getIsReportArkAttribute()
    {
        return !$this->containers->contains('ContainerNumber', '');//只要有一个柜没柜号都当未录
    }

    /**
     * 获取装拆封箱图片上传已否
     * @reviser czm
     * @return bool
     */
    public function getIsLoadImgAttribute()
    {
        //只要有一个柜没上够两图片都当未上图片
        $allLoaded = !$this->containers->contains(function ($container) {
            if ($this->isLoad()) {
                return !$container->LoadCantainerImage || !$container->LoadSealingImage;
            } else {
                return !$container->UnloadCantainerImage;
            }
        });
        return $allLoaded;
    }

    
    /**
     * 时间与状态名映射
     * @var array
     */
    public $boolTimeField = [
        'DriverSure' => 'DriverAcceptOrderTime',
        'depart_truck' => 'DriverStartUpTime',
        'is_affirm_boxnum' => 'AffirmContainerTime',
        'isReceipt' => 'BackElectronicDucumentTime',
        'return_port' => 'CompleteDeliveryTime',
    ];

    /**
     * 将时间信息转为状态信息
     * @reviser czm
     * @lastModify 2019/5/27 11:56
     * @return $this
     */
    public function fillTimeBool()
    {
        $boolTimeField = array_only(
            $this->boolTimeField,
            array_keys($this->append('MissionField')->MissionField)
        );
        $boolField     = [];
        foreach ($boolTimeField as $index => $field) {
            $boolField[$index] = (bool)strtotime($this->attributes[$field]);
        }
        $this->forceFill($boolField);
        return $this;
    }

    /**
     * 流程管理的状态填充
     * @reviser czm
     * @return hlyun_order_trailer_dispaction
     */
    public function fillMission()
    {
        $shouldAppend = array_intersect(
            ['isReportArk', 'isLoadImg', 'PushCabinet', 'PushReceipt'],
            array_keys($this->append('MissionField')->MissionField)
        );
        return $this->append($shouldAppend);
    }

    /**
     * 柜状态流程说明
     * @reviser czm
     * @return array
     * @throws \Exception
     */
    public function getMissionFieldAttribute()
    {
        $missionField = [
            'DriverSure' => '接单',
            'depart_truck' => '发车',
            'isReportArk' => '报柜号',
            'is_affirm_boxnum' => '确认箱号',
            'isLoadImg' => [1 => '装箱图片', 0 => '拆箱图片'],
            'PushCabinet' => '推送报箱',
            'IsRecover' => '代收款',
            // 'isReceipt' => '电子回单',
            'PushReceipt' => '签收单',
            'return_port' => '完成',
        ];
        if ($this->isLoad()) { //装货特有的流程
            unset($missionField['is_affirm_boxnum']);
            $missionField['isLoadImg'] = $missionField['isLoadImg'][1];
            $shouldWay                 = 1;
        } else { //送货特有流程
            unset($missionField['isReportArk']);
            $shouldWay                 = 2;
            $missionField['isLoadImg'] = $missionField['isLoadImg'][0];
        }
        if (!($this->isLoad() && $this->isAnTong())) { //送货或非安通委托则不用推送报箱
            unset($missionField['PushCabinet']);
        }
        // if ($this->isLoad() || !$this->isAnTong()) { //装货或不是安通委托则不用传签收单
        //     unset($missionField['PushReceipt']);
        // }
        if ($this->IsReplaceCharge != 1 || $this->ReplaceChargeWay != $shouldWay) { //订单不指定代收则不用代收
            unset($missionField['IsRecover']);
        }
        return $missionField;
    }


}
