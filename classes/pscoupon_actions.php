<?php
  class PSCoupon_Actions extends Shop_Actions {
    public function on_setCouponCode($allow_redirect = true) {
      $_POST['coupon'] = PSCoupon_Module::filter_coupon_code(trim(post('coupon')));
      parent::on_setCouponCode($allow_redirect);
    }
  }