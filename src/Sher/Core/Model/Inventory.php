<?php
/**
 * 产品sku库存管理
 * 预售档位作为一个sku处理
 * @author purpen
 */
class Sher_Core_Model_Inventory extends Sher_Core_Model_Base  {

    protected $collection = "inventory";
	
	protected $mongo_id_style = DoggyX_Model_Mongo_Base::MONGO_ID_CUSTOM;
	
    protected $schema = array(
		# sku
		'_id' => null,
		
		'product_id' => 0,
		
		# 名称
		'name' => '',
		
		# 颜色/型号
		'mode'  => '',
		# 销售价格
		'price' => '',
		# 库存总数
		'quantity' => 0,
		# 已销售数量
		'sold'  => 0,
		
		# 限额数量
		'limited_count' => 0,
		
		# 同步数量
		'sync_count' => 0,
		
		# 描述
		'summary' => '',
		
		# 损坏数量
		'bad_count' => 0,
		'bad_tag' => '',
		
		# 撤回数量
		'revoke_count' => 0,
		
		# 货架编号
		'shelf' => 0,
		
		'status' => 0,
    );
	
    protected $joins = array(
    	'product' => array('product_id' => 'Sher_Core_Model_Product'),
    );
	
    protected $required_fields = array('product_id', 'price', 'quantity');
    protected $int_fields = array('product_id', 'quantity', 'limited_count', 'sold', 'sync_count', 'revoke_count');
	
	/**
	 * 扩展关联数据
	 */
    protected function extra_extend_model_row(&$row) {
    	if(isset($row['sync_count'])){
    		$row['sold'] += $row['sync_count'];
    	}
    }
	
	// 添加自定义ID
    protected function before_insert(&$data) {
        $data['_id'] = $this->gen_product_sku();
		Doggy_Log_Helper::warn("Create product new sku ".$data['_id']);
		
		parent::before_insert($data);
    }
	
	/**
	 * 保存之后事件
	 */
    protected function after_save() {
		// 更新产品sku count
		$product_id = $this->data['product_id'];
		Doggy_Log_Helper::debug("After save inventory:[$product_id]! ");
		$field = 'sku_count';
		if (!empty($product_id)) {
			$product = new Sher_Core_Model_Product();
			$product->inc_counter($field, 1, (int)$product_id);
			unset($product);
			
			$this->recount_product_inventory($product_id, 1);
		}
    }
	
	/**
	 * 删除后事件
	 */
	public function mock_after_remove($product_id) {
		$field = 'sku_count';
		// 更新产品sku count
		if (!empty($product_id)) {
			$product = new Sher_Core_Model_Product();
			$product->dec_counter($field, (int)$product_id);
			unset($product);
			
			$this->recount_product_inventory($product_id, 1);
		}
		
		return true;
	}
	
	/**
	 * 重新计算产品总库存数量
	 */
	public function recount_product_inventory($product_id, $stage, $updated=true){
		$result = $this->find(array(
			'product_id' => (int)$product_id,
		));
		
		$inventory = 0;
		if (!empty($result)){
			for($i=0;$i<count($result);$i++){
				$inventory += $result[$i]['quantity'];
			}
		}
		
		Doggy_Log_Helper::debug("Recount product inventory:[$product_id],[$inventory]! ");
		
		$field = 'inventory';
		
		// 直接返回库存数量
		if(!$updated){
			return $inventory;
		}
		
		$product = new Sher_Core_Model_Product();
		$product->update_set((int)$product_id, array($field => $inventory));
		unset($product);
	}
	
	/**
	 * 更新同步数量
	 */
	public function update_sync_count($sku, $sync_count=0, $sync_people=0){
		$add_money = 0;
		$only = false;
		$sku = (int)$sku;
		$sync_count = (int)$sync_count;
		$sync_people = (int)$sync_people;
		
		$item = $this->find_by_id($sku);
		
		if(!empty($item)){
			if($item['quantity'] < $sync_count){
				throw new Sher_Core_Model_Exception('库存数量不足！');
			}
			Doggy_Log_Helper::warn("Update product[$sku] sync count[$sync_count]!");
			$updated = array(
				'$inc' => array('quantity'=>$sync_count*-1, 'sync_count'=>$sync_count),
			);
			$ok = $this->update($sku, $updated);
			
			$product_id = $item['product_id'];
			
			// 新增金额
			$add_money  = $item['price']*$sync_count;
		} else {
			// 仅有1个默认sku
			$product_id = $sku;
			$only = true;
		}
		
		// 更新总库存数
		$product = new Sher_Core_Model_Product();
		$product->decrease_invertory($product_id, $sync_count, $only, $add_money, $sync_people);
		
		unset($product);
	}
	
	/**
	 * 减少库存数量
	 */
	public function decrease_invertory_quantity($sku, $need_quantity=1){
		$add_money = 0;
		$only = false;
		$item = $this->find_by_id((int)$sku);
		if(!empty($item)){
			$updated = array(
				'$inc' => array('quantity'=>$need_quantity*-1, 'sold'=>$need_quantity),
			);
			$this->update((int)$sku, $updated);
			
			$product_id = $item['product_id'];
			
			// 新增金额
			$add_money = $item['price']*$need_quantity;
		} else {
			// 仅有1个默认sku
			$product_id = $sku;
			$only = true;
		}
		
		// 更新总库存数
		$product = new Sher_Core_Model_Product();
		$product->decrease_invertory($product_id, $need_quantity, $only, $add_money);
		
		unset($product);
	}
	
	/**
	 * 恢复库存数量
	 */
	public function recover_invertory_quantity($sku, $sale_quantity=1){
		$dec_money = 0;
		$only = false;
		$item = $this->find_by_id((int)$sku);
		if(!empty($item)){
			$updated = array(
				'$inc' => array('quantity'=>$sale_quantity, 'sold'=>$sale_quantity*-1),
			);
			$this->update((int)$sku, $updated);
			
			$product_id = $item['product_id'];
			
			// 减少金额
			$dec_money = $item['price']*$sale_quantity;
		} else {
			// 仅有1个默认sku
			$product_id = $sku;
			$only = true;
		}
		
		// 更新总库存数
		$product = new Sher_Core_Model_Product();
		$product->recover_invertory($product_id, $sale_quantity, $only, $dec_money);
		
		unset($product);
	}
	
	/**
	 * 验证库存数量是否足够
	 */
	public function verify_enough_quantity($sku, $need_quantity=1) {
		$item = $this->find_by_id((int)$sku);
		if (!empty($item)){
			return $item['quantity'] >= $need_quantity;
		} else {
			// 仅有1个默认sku
			$product = new Sher_Core_Model_Product();
			$row = $product->find_by_id((int)$sku);
			
			return $row['inventory'] >= $need_quantity;
		}
		
		return false;
	}
	
	/**
	 * 生成产品的SKU, SKU十位数字符
	 */
	protected function gen_product_sku($prefix='1'){
		$name = Doggy_Config::$vars['app.serialno.name'];
		
		$sku  = $prefix;
		$val = $this->next_seq_id($name);
		
		$len = strlen((string)$val);
		if ($len <= 5) {
			$sku .= date('md');
			$sku .= sprintf("%05d", $val);
		}else{
			$sku .= substr(date('md'), 0, 9 - $len);
			$sku .= $val; 
		}
		
		Doggy_Log_Helper::debug("Gen to product [$sku]");
		
		return (int)$sku;
	}
	
}

