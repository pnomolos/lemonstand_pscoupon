<?php

  class PSCoupon_Module extends Core_ModuleBase
  {
    public static $salt = 'SALT';
    /**
     * Creates the module information object
     * @return Core_ModuleInfo
     */
    protected function createModuleInfo() {
      return new Core_ModuleInfo(
        "Coupon Module",
        "Developer module to aid the use of coupon aliases",
        "Philip Schalm" );
    }
    
    public function subscribeEvents() {
      Backend::$events->addEvent('shop:onBeforeOrderRecordCreate', $this, 'before_order_record_create');
      Backend::$events->addEvent('shop:onOrderStatusChanged', $this, 'order_status_changed');
      Backend::$events->addEvent('shop:onNewOrder', $this, 'new_order');
    }
    
    public static function create_coupon($code_or_length = null, $key = null, $shop_coupon_id = null, $order_status = 'paid', $delete_on_use = false) {
      $code = null;
      if ( is_string($code_or_length) ) {
        $code = $code_or_length;
      } else {
        $length = is_int($code_or_length) ? $code_or_length : 15;
        if ( !$key ) { $key = mt_rand(); }
        $code = substr( sha1( self::$salt . microtime() . $key . $_SERVER['REMOTE_ADDR'] ), 0, $length );
      }
      if ( !$code ) { return false; }
      
      // Handle the coupon code
      if ( is_object($shop_coupon_id) && get_class($shop_coupon_id) == 'Shop_Coupon' ) {
        $shop_coupon_id = $shop_coupon_id->id;
      } else if ( is_int($shop_coupon_id) ) {
        $shop_coupon_id = Db_DbHelper::scalar('SELECT id FROM shop_coupons WHERE id = :id', array('id' => $shop_coupon_id));
      } else if ( !is_null($shop_coupon_id) ) {
        $shop_coupon_id = Db_DbHelper::scalar('SELECT id FROM shop_coupons WHERE code = :code', array('code' => $shop_coupon_id));
      } else {
        return false;
      }
      
      // Handle the order status
      // There is a special case where $order_status_id = 0: 
      //   the coupon will be handled in the 'shop:onBeforeOrderRecordCreate' event
      //   rather than in the 'shop:onOrderStatusChanged' event
      $order_status_id = null;
      if ( is_object($order_status) && get_class($order_status) == 'Shop_OrderStatus' ) {
        $order_status_id = $order_status->id;
      } else if ( is_int($order_status) ) {
        if ( $order_status === 0 ) {
          $order_status_id = null;
        } else {
          $order_status_id = Db_DbHelper::scalar('SELECT id FROM shop_order_statuses WHERE id = :id', array('id' => $order_status));          
        }
      } else if ( !is_null($order_status) ) {
        $order_status_id = Db_DbHelper::scalar('SELECT id FROM shop_order_statuses WHERE code = :code', array('code' => $order_status));
      }
      if ( $order_status !== 0 && !$order_status_id ) { return false; }
      
      Db_DbHelper::query('INSERT INTO 
        pscoupon_coupons(code, shop_coupon_id, shop_order_status_id, used, delete_on_use) 
        VALUES(:code, :shop_coupon_id, :shop_order_status_id, 0, :delete_on_use)
      ',array(
        'code' => $code,
        'shop_coupon_id' => $shop_coupon_id,
        'shop_order_status_id' => $order_status_id,
        'delete_on_use' => (int)$delete_on_use
      ));
      return Db_DbHelper::object('SELECT * FROM pscoupon_coupons WHERE code = :code', array('code' => $code));
    }
    
    public static function delete_coupon($code_or_id) {
      if ( is_int($code_or_id) ) {
        return Db_DbHelper::query('DELETE FROM pscoupon_coupons WHERE id = :id', array('id' => $code_or_id));
      } else {
        return Db_DbHelper::query('DELETE FROM pscoupon_coupons WHERE code = :code', array('id' => $code_or_id));
      }
    }

    public static function reverse_filter_coupon_code($code = null) {
      if ( !$code ) { return $code; }
      
      $id = Cms_VisitorPreferences::get('pscoupon_id');
      if ( !$id ) { return $code; }
      
      $pscoupon_code = Db_DbHelper::scalar('SELECT pscoupon_coupons.code FROM pscoupon_coupons INNER JOIN shop_coupons ON pscoupon_coupons.shop_coupon_id = shop_coupons.id WHERE shop_coupons.code = :code AND pscoupon_coupons.id = :id', array('code' => $code, 'id' => $id));
      return $pscoupon_code ? $pscoupon_code : $code;
    }
    
    public static function filter_coupon_code($code = null) {
      if ( !$code ) { return $code; }
      
      $pscoupon = Db_DbHelper::object('SELECT pscoupon_coupons.*, shop_coupons.code AS shop_coupon_code FROM pscoupon_coupons INNER JOIN shop_coupons ON pscoupon_coupons.shop_coupon_id = shop_coupons.id WHERE pscoupon_coupons.code = :coupon_code AND ( used IS NULL OR used = 0 )', array('coupon_code' => $code));
      
      if ( !is_object( $pscoupon ) || !$pscoupon->shop_coupon_code ) {
        return $code;
      }
    
      Cms_VisitorPreferences::set('pscoupon_id', $pscoupon->id);
      return $pscoupon->shop_coupon_code;
    }
    
    public function new_order($order_id) {
      if (($pscoupon_id = Cms_VisitorPreferences::get('pscoupon_id'))) {
        Db_DbHelper::query('UPDATE pscoupon_coupons SET shop_order_id = :order_id WHERE id = :id',array(
          'order_id' => $order_id,
          'id' => $pscoupon_id
        ));
        Cms_VisitorPreferences::set('pscoupon_id', null);
      }
    }
    
    public function order_status_changed($order, $new_status, $prev_status_id) {
      $pscoupon = Db_DbHelper::object('SELECT * FROM pscoupon_coupons WHERE 
        shop_order_id = :order_id AND shop_order_status_id = :order_status_id', array(
        'order_id' => $order->id,
        'order_status_id' => $new_status->id
      ));
      
      if (!$pscoupon) { return; }
      self::use_code( $pscoupon );
    }
    
    public function before_order_record_create($order, $session_key) {
      if ( !$order->coupon || !$order->coupon->code ) {
        return;
      }
      
      $filtered_coupon_code = self::reverse_filter_coupon_code($order->coupon->code);
      if ( $filtered_coupon_code && $filtered_coupon_code != $order->coupon->code ) {
        $pscoupon = Db_DbHelper::object('SELECT * FROM pscoupon_coupons WHERE code = :code AND shop_order_status_id IS NULL', 
          array('code' => $filtered_coupon_code)
        );
        if ($pscoupon) { 
          self::use_code( $pscoupon );
        }
      }
    }
    
    private static function use_code($pscoupon = null) {
      if ( !$pscoupon ) { return false; }
      if (!is_object($pscoupon)) {
        $pscoupon = Db_DbHelper::object('SELECT * FROM pscoupon_coupons WHERE id = :id', array('id' => $pscoupon_id));
      }
      if ( $pscoupon->delete_on_use ) {
        Db_DbHelper::query('DELETE FROM pscoupon_coupons WHERE id = :id', array('id' => $pscoupon->id));
      } else {
        Db_DbHelper::query('UPDATE pscoupon_coupons SET used = 1 WHERE id = :id', array('id' => $pscoupon->id));
      }
      Cms_VisitorPreferences::set('pscoupon_id', null);
      return true;
    }
  }
