<?php
namespace app\controller;
use app\service\ExcelExportService;
use app\service\OutstorageService,
    app\model\Product,
    app\model\Supplier,
    app\model\Unit,
    app\model\Statistics as StatisticsModel,
    app\model\Order;
use app\service\StatisticsService;
use think\Request;

class Statistics extends Base
{
    protected $service;
    public function __construct(StatisticsService $service)
    {
        parent::__construct();
        $this->service = $service;
    }  

    public function index()
    {
        $this->assign(['list'  =>  $this->service->page()]);
        return view();
    }

    public function create()
    {
        $data 	= Request::instance()->get();
        $product = Product::all(  [ 'status' => 0, 'state' => 2 ] );
        foreach ($product as $key => $val){
            $unit1 = Unit::get(['id', $val['unit1']]);
            $unit2 = Unit::get(['id', $val['unit2']]);
            $unit3 = Unit::get(['id', $val['unit3']]);
            $product[$key]['unit1'] = '';
            $product[$key]['unit2'] = '';
            $product[$key]['unit3'] = '';
            if($unit1){
                $product[$key]['unit1'] = $unit1['name'];
            }
            if($unit2){
                $product[$key]['unit2'] = $unit2['name'];
            }
            if($unit3){
                $product[$key]['unit3'] = $unit3['name'];
            }
        }
        $this->assign([
            'product'    =>  $product,
            'product_finish' =>  Product::all(  [ 'status' => 0, 'state' => 1 ] )
        ]);

        return view();
    }

    public function spec(){
        $data 	= Request::instance()->get();
        $product = Product::get(['id', $data['product_id']]);

        $unit = [];
        $unit1 = Unit::get(['id', $product['unit1']]);
        $unit2 = Unit::get(['id', $product['unit2']]);
        $unit3 = Unit::get(['id', $product['unit3']]);
        if($unit1){
            $unit = $unit1['name'];
        }
        if($unit2){
            $unit = $unit2['name'];
        }
        if($unit3){
            $unit = $unit3['name'];
        }

        if(!$unit){
            $unit[] = $product['unit'];
        }
        $specs = \db('product_spec')->where('product_id', $data['product_id'])->select();
        $this->assign('specs', $specs);
        $this->assign('units', $unit);

        return view();
    }

    public function show($id)
    {
        $this->assign([
            'product'    =>  Product::all(  [ 'status' => 0,'state' => 2] ),
            'info'       =>  StatisticsModel::get(['id'=>$id]),
            'product_finish' =>  Product::all(  [ 'status' => 0, 'state' => 1 ] )
        ]);
        return view();
    }

    public function save()
    {
        return $this->service->save();
    }

    public function edit($id)
    {
        $this->assign([
            'product'    =>  Product::all(  [ 'status' => 0 ,'state' => 2] ),
            'supplier'   =>  Supplier::all(  [ 'status' => 0 ] ),
            'info'  =>  $this->service->edit($id),
            'product_finish' =>  Product::all(  [ 'status' => 0, 'state' => 1 ] )

        ]);

        return view();
    }

    public function update(){
        return $this->service->update();
    }

    public function delete($id){
        return $this->service->delete($id);
    }

    public function dobudget(){
        $param = Request::instance()->param();	//获取参数
        $statistics = StatisticsModel::get(['id' => $param['id']]);
        $pid = $statistics['product_id'];
        $res = json_decode($statistics['res'], true);

        $product = \db('product')->find($pid);
        $data = array(
            'num' => 0,
            'msg' => '该商品还没有设置材料'
        );
        if($res){
            $canMakeNum = 0;
            if($res){
                $successNum = [];
                foreach ($res as $val){
                    $store = db('product_spec')->where('product_id', $val[0])->sum('store');
                    if($store){
                        $successNum[] = bcdiv($store, $val[3], 0);
                    }
                }

                if(count($successNum) == count($res)){
                    $canMakeNum = min($successNum);
                }
            }
            $productModel = new Product();

            $msg = '部分材料不足，不能生产产品';
            if($canMakeNum){
                $msg = '可以生产'.$product['name'].$canMakeNum.$product['unit'].'，初步统计：'.$productModel->changeUnit($pid, $canMakeNum);
            }

            $data = array(
                'num' => $canMakeNum,
                'msg' => $msg
            );
        }

        $this->assign('info', $data);

        return view();
    }

    public function barcode($sn){
        include ROOT_PATH.'/extend/Barcode.php';
        die( \Barcode::code39($sn) );  
    }
    public function export(){
        $param = Request::instance()->get();
        $data = $this->service->page()->toArray();
        $name=$param['start_time'].'至'.$param['end_time'].'出库单';
        $header=[['id','出库单id'],['sn','出库单编号'],['author', '制表人'],['supplier', '供应商'], ['type', '出库类型'], ['outstorage_curator','保管员'], ['outstorage_consignee','提货人'], ['add_time','创单时间'],['info','出库单详情'] ,['desc','备注']];
        $newdata = [];
        foreach ($data['data'] as $key => $value){
            $newdata[$key]['id'] = $value['id'];
            $newdata[$key]['sn'] = $value['sn'];
            $newdata[$key]['author'] = $value['author'];
            $newdata[$key]['supplier'] = $value['supplier'];
            $newdata[$key]['type'] = $value['type'];
            $newdata[$key]['outstorage_curator'] = $value['outstorage_curator'];
            $newdata[$key]['outstorage_consignee'] = $value['outstorage_consignee'];
            $newdata[$key]['add_time'] = date('Y年m月d日 H时i分s秒', $value['add_time']);
            $res = json_decode($value['res'], true);
            $detail = '';
            foreach ($res as $val){
                $detail = $detail.'商品编号：'.$val[0].'  商品名称：'.$val[1]. ' 入库数量：'.$val[2].$val[5]. ' 包装：'.$val[4].' ||';
            }
            $newdata[$key]['info'] = $detail;
            $newdata[$key]['desc'] = $value['desc'];
        }

        $excel = new ExcelExportService();
        $excel->exportExcel($name,$header,$newdata);

    }
}
